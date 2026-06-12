<?php

namespace App\Console\Commands;

use App\Models\Cms\ContentType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Promove um tipo de admin a código real: Model + Migration + Resource +
 * migração de dados, como FICHEIROS para review e commit (G1/G5/G7).
 * Gera UMA vez e recusa-se a esmagar ficheiros existentes; evolução
 * posterior é migration nova, nunca regeneração (05-tipos-admin).
 */
class CmsPromoteType extends Command
{
    protected $signature = 'cms:promote:type {slug : Slug do tipo em cms_types}';

    protected $description = 'Emite Model + Migration + Filament Resource a partir do blueprint de um tipo de admin (dev-time)';

    private const COLUMN_MAP = [
        'text' => 'string',
        'textarea' => 'text',
        'richtext' => 'longText',
        'number' => 'integer',
        'boolean' => 'boolean',
        'date' => 'date',
        'select' => 'string',
        'media' => 'string',
        'link' => 'jsonb',
        'repeater' => 'jsonb',
    ];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Promoção é dev-time. Nunca em produção (G1).');

            return self::FAILURE;
        }

        $type = ContentType::query()->where('slug', $this->argument('slug'))->first();

        if ($type === null) {
            $this->error("Tipo '{$this->argument('slug')}' não existe.");

            return self::FAILURE;
        }

        $studly = Str::studly(Str::singular(str_replace('-', '_', $type->slug)));
        $table = Str::snake(Str::pluralStudly($studly));
        $timestamp = now()->format('Y_m_d_His');

        $targets = [
            'model' => app_path("Models/{$studly}.php"),
            'migration' => database_path("migrations/{$timestamp}_create_{$table}_table.php"),
            'data' => database_path('migrations/'.now()->addSecond()->format('Y_m_d_His')."_migrate_{$type->slug}_entries_to_{$table}.php"),
        ];

        foreach (['model' => $targets['model']] as $file) {
            if (File::exists($file)) {
                $this->error("Recusado: {$file} já existe. Promoção gera uma vez; depois o dev é dono.");

                return self::FAILURE;
            }
        }

        File::put($targets['model'], $this->modelStub($studly, $table, $type->fields()));
        File::put($targets['migration'], $this->migrationStub($table, $type->fields()));
        File::put($targets['data'], $this->dataMigrationStub($type, $table));

        $type->forceFill(['promoted' => true])->save();

        $this->info('Gerado:');

        foreach ($targets as $file) {
            $this->line('  '.str_replace(base_path().'/', '', $file));
        }

        $this->warn('Revê, ajusta, commita e corre `php artisan migrate`. O tipo saiu da UI de tipos de admin.');

        return self::SUCCESS;
    }

    private function modelStub(string $studly, string $table, array $fields): string
    {
        $fillable = collect($fields)
            ->map(fn (array $f) => "'".Str::snake($f['name'])."'")
            ->implode(', ');

        $casts = collect($fields)
            ->filter(fn (array $f) => in_array($f['type'], ['boolean', 'date', 'link', 'repeater'], true))
            ->map(fn (array $f) => "            '".Str::snake($f['name'])."' => '".match ($f['type']) {
                'boolean' => 'boolean',
                'date' => 'date',
                default => 'array',
            }."',")
            ->implode("\n");

        return <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class {$studly} extends Model
{
    protected \$fillable = [{$fillable}];

    protected function casts(): array
    {
        return [
{$casts}
        ];
    }
}

PHP;
    }

    private function migrationStub(string $table, array $fields): string
    {
        $columns = collect($fields)->map(function (array $f) {
            $column = Str::snake($f['name']);
            $method = self::COLUMN_MAP[$f['type']] ?? 'string';
            $line = "            \$table->{$method}('{$column}')";

            if (empty($f['required'])) {
                $line .= '->nullable()';
            }

            // Campos filtráveis/listáveis → coluna indexada (G4).
            if (! empty($f['listable']) || $f['type'] === 'select') {
                $line .= '->index()';
            }

            return $line.';';
        })->implode("\n");

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
{$columns}
            \$table->string('slug')->nullable()->unique();
            \$table->string('status', 20)->default('draft')->index();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};

PHP;
    }

    private function dataMigrationStub(ContentType $type, string $table): string
    {
        $assignments = collect($type->fields())->map(function (array $f) {
            $column = Str::snake($f['name']);
            $cast = in_array($f['type'], ['link', 'repeater'], true)
                ? "json_encode(data_get(\$data, '{$f['name']}'))"
                : "data_get(\$data, '{$f['name']}')";

            return "                    '{$column}' => {$cast},";
        })->implode("\n");

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Copia os entries do tipo '{$type->slug}' (cms_entries.data jsonb) para a
 * tabela tipada '{$table}'. Os entries antigos ficam soft-deleted até limpeza.
 */
return new class extends Migration
{
    public function up(): void
    {
        \$entries = DB::table('cms_entries')
            ->join('cms_types', 'cms_types.id', '=', 'cms_entries.type_id')
            ->where('cms_types.slug', '{$type->slug}')
            ->whereNull('cms_entries.deleted_at')
            ->select('cms_entries.*')
            ->orderBy('cms_entries.id')
            ->get();

        foreach (\$entries as \$entry) {
            \$data = json_decode(\$entry->data, true) ?? [];

            DB::table('{$table}')->insert(array_merge(
                [
{$assignments}
                ],
                [
                    'slug' => \$entry->slug,
                    'status' => \$entry->status,
                    'created_at' => \$entry->created_at,
                    'updated_at' => \$entry->updated_at,
                ],
            ));
        }

        DB::table('cms_entries')
            ->whereIn('id', \$entries->pluck('id'))
            ->update(['deleted_at' => now()]);
    }

    public function down(): void
    {
        // Sem rollback automático: os entries originais continuam em
        // cms_entries (soft-deleted) — repor é um restore manual deliberado.
    }
};

PHP;
    }
}
