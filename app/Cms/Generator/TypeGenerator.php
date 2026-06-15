<?php

namespace App\Cms\Generator;

use App\Models\Cms\ContentType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Transforma um ContentType (designer: campos + relações) em código real:
 * Model + Migration + FilamentResource. Os ficheiros entram no git e a tabela
 * é tipada (colunas reais, índices, FKs) — o oposto do EAV.
 *
 * NOTA: por decisão do utilizador, este gerador pode correr a partir do admin
 * em qualquer ambiente (o blueprint original proibia codegen em runtime — G1).
 * Mantém-se a salvaguarda de não esmagar ficheiros já existentes.
 */
class TypeGenerator
{
    /** Tabelas de models do core, para resolver FKs/títulos de relações. */
    private const CORE_TABLES = [
        \App\Models\Cms\Page::class => ['table' => 'cms_pages', 'title' => 'name'],
        \App\Models\Cms\Menu::class => ['table' => 'cms_menus', 'title' => 'name'],
        \App\Models\User::class => ['table' => 'users', 'title' => 'name'],
    ];

    public function studly(ContentType $type): string
    {
        return Str::studly(Str::singular(str_replace('-', '_', $type->slug)));
    }

    public function table(ContentType $type): string
    {
        return Str::snake(Str::pluralStudly($this->studly($type)));
    }

    public function modelClass(ContentType $type): string
    {
        return 'App\\Models\\'.$this->studly($type);
    }

    public function modelPath(ContentType $type): string
    {
        return app_path('Models/'.$this->studly($type).'.php');
    }

    public function exists(ContentType $type): bool
    {
        return File::exists($this->modelPath($type));
    }

    /**
     * Gera todos os ficheiros. Devolve a lista de paths (relativos ao base).
     *
     * @throws \RuntimeException se o model já existir (não esmaga edições à mão).
     */
    public function generate(ContentType $type): array
    {
        if ($this->exists($type)) {
            throw new \RuntimeException(
                "O model {$this->studly($type)} já existe. A geração corre uma vez; ".
                'depois o dev é dono do ficheiro (evolução = nova migration).',
            );
        }

        $now = Carbon::now();
        $written = [];

        $written[] = $this->put($this->modelPath($type), $this->modelStub($type));
        $written[] = $this->put($this->migrationPath($type, $now), $this->migrationStub($type));

        foreach ($this->belongsToMany($type) as $i => $relation) {
            $path = $this->pivotMigrationPath($type, $relation, $now->copy()->addSeconds($i + 1));
            $written[] = $this->put($path, $this->pivotMigrationStub($type, $relation));
        }

        $dir = app_path('Filament/Resources/'.$this->studly($type));
        $written[] = $this->put($dir.'/'.$this->studly($type).'Resource.php', $this->resourceStub($type));
        $written[] = $this->put($dir.'/Pages/List'.$this->studly($type).'.php', $this->pageStub($type, 'List', 'ListRecords'));
        $written[] = $this->put($dir.'/Pages/Create'.$this->studly($type).'.php', $this->pageStub($type, 'Create', 'CreateRecord'));
        $written[] = $this->put($dir.'/Pages/Edit'.$this->studly($type).'.php', $this->pageStub($type, 'Edit', 'EditRecord'));

        $type->forceFill(['generated' => true])->save();

        return $written;
    }

    private function put(string $path, string $contents): string
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);

        return str_replace(base_path().'/', '', $path);
    }

    // ---- Migration ---------------------------------------------------------

    private function migrationPath(ContentType $type, Carbon $now): string
    {
        return database_path('migrations/'.$now->format('Y_m_d_His').'_create_'.$this->table($type).'_table.php');
    }

    private function migrationStub(ContentType $type): string
    {
        $table = $this->table($type);
        $lines = [];

        foreach ($type->fields() as $field) {
            $lines[] = '            '.$this->columnFor($field);
        }

        foreach ($this->belongsTo($type) as $relation) {
            $lines[] = '            $table->unsignedBigInteger(\''.$this->fkColumn($relation).'\')->nullable()->index();';
        }

        $columns = implode("\n", $lines);

        // FKs de belongsTo adicionadas condicionalmente: robusto independente da
        // ordem de geração (a tabela alvo pode ainda não existir).
        $foreignKeys = '';
        foreach ($this->belongsTo($type) as $relation) {
            $targetTable = $this->targetTable($relation['target']);
            $fk = $this->fkColumn($relation);
            $foreignKeys .= <<<PHP

        if (Schema::hasTable('{$targetTable}')) {
            Schema::table('{$table}', function (Blueprint \$table) {
                \$table->foreign('{$fk}')->references('id')->on('{$targetTable}')->nullOnDelete();
            });
        }
PHP;
        }

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
{$foreignKeys}
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};

PHP;
    }

    private function columnFor(array $field): string
    {
        $name = Str::snake($field['name']);
        $required = ! empty($field['required']);
        $indexed = ! empty($field['listable']) || ($field['type'] ?? '') === 'select';

        $method = match ($field['type'] ?? 'text') {
            'textarea' => 'text',
            'richtext' => 'longText',
            'number' => 'integer',
            'boolean' => 'boolean',
            'date' => 'date',
            'media', 'link', 'select' => 'string',
            'menu' => 'unsignedBigInteger',
            'repeater' => 'jsonb',
            default => 'string',
        };

        $line = '$table->'.$method.'(\''.$name.'\')';

        if (! $required) {
            $line .= '->nullable()';
        }

        if ($indexed && ! in_array($method, ['text', 'longText', 'jsonb'], true)) {
            $line .= '->index()';
        }

        return $line.';';
    }

    // ---- Pivots (belongsToMany) -------------------------------------------

    private function pivotTable(ContentType $type, array $relation): string
    {
        $a = Str::snake(Str::singular($this->studly($type)));
        $b = Str::snake(Str::singular(class_basename($relation['target'])));
        $pair = [$a, $b];
        sort($pair);

        return implode('_', $pair);
    }

    private function pivotMigrationPath(ContentType $type, array $relation, Carbon $now): string
    {
        return database_path('migrations/'.$now->format('Y_m_d_His').'_create_'.$this->pivotTable($type, $relation).'_table.php');
    }

    private function pivotMigrationStub(ContentType $type, array $relation): string
    {
        $pivot = $this->pivotTable($type, $relation);
        $thisCol = Str::snake(Str::singular($this->studly($type))).'_id';
        $targetCol = Str::snake(Str::singular(class_basename($relation['target']))).'_id';
        $thisTable = $this->table($type);
        $targetTable = $this->targetTable($relation['target']);

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$pivot}', function (Blueprint \$table) {
            \$table->unsignedBigInteger('{$thisCol}');
            \$table->unsignedBigInteger('{$targetCol}');
            \$table->primary(['{$thisCol}', '{$targetCol}']);
        });

        if (Schema::hasTable('{$thisTable}') && Schema::hasTable('{$targetTable}')) {
            Schema::table('{$pivot}', function (Blueprint \$table) {
                \$table->foreign('{$thisCol}')->references('id')->on('{$thisTable}')->cascadeOnDelete();
                \$table->foreign('{$targetCol}')->references('id')->on('{$targetTable}')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('{$pivot}');
    }
};

PHP;
    }

    // ---- Model -------------------------------------------------------------

    private function modelStub(ContentType $type): string
    {
        $studly = $this->studly($type);

        $fillable = collect($type->fields())
            ->map(fn (array $f) => "'".Str::snake($f['name'])."'");

        foreach ($this->belongsTo($type) as $relation) {
            $fillable->push("'".$this->fkColumn($relation)."'");
        }
        $fillable->push("'slug'", "'status'");
        $fillableStr = $fillable->implode(', ');

        $casts = collect($type->fields())
            ->filter(fn (array $f) => in_array($f['type'], ['boolean', 'date', 'repeater'], true))
            ->map(fn (array $f) => "            '".Str::snake($f['name'])."' => '".match ($f['type']) {
                'boolean' => 'boolean',
                'date' => 'date',
                default => 'array',
            }."',")
            ->implode("\n");

        $relationMethods = collect($type->relationDefs())
            ->map(fn (array $r) => $this->relationMethod($r))
            ->filter()
            ->implode("\n\n");

        return <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class {$studly} extends Model
{
    protected \$fillable = [{$fillableStr}];

    protected function casts(): array
    {
        return [
{$casts}
        ];
    }

{$relationMethods}
}

PHP;
    }

    private function relationMethod(array $relation): string
    {
        $name = Str::camel($relation['name']);
        $target = '\\'.ltrim($relation['target'], '\\');

        return match ($relation['type']) {
            'belongsTo' => <<<PHP
    public function {$name}(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return \$this->belongsTo({$target}::class, '{$this->fkColumn($relation)}');
    }
PHP,
            'hasMany' => <<<PHP
    public function {$name}(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return \$this->hasMany({$target}::class);
    }
PHP,
            'belongsToMany' => <<<PHP
    public function {$name}(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return \$this->belongsToMany({$target}::class);
    }
PHP,
            default => '',
        };
    }

    // ---- Resource ----------------------------------------------------------

    private function resourceStub(ContentType $type): string
    {
        $studly = $this->studly($type);
        $icon = $type->icon ?: 'heroicon-o-rectangle-stack';

        $formComponents = [];
        foreach ($type->fields() as $field) {
            $formComponents[] = '                '.$this->formInputFor($field).',';
        }
        foreach ($this->belongsTo($type) as $relation) {
            $formComponents[] = '                '.$this->relationInput($relation).',';
        }
        foreach ($this->belongsToMany($type) as $relation) {
            $formComponents[] = '                '.$this->relationInput($relation).',';
        }
        $form = implode("\n", $formComponents);

        $tableCols = [];
        foreach ($this->listableFields($type) as $field) {
            $name = Str::snake($field['name']);
            $tableCols[] = "                \\Filament\\Tables\\Columns\\TextColumn::make('{$name}')->searchable(),";
        }
        $tableCols[] = "                \\Filament\\Tables\\Columns\\TextColumn::make('status')->badge(),";
        $tableCols[] = "                \\Filament\\Tables\\Columns\\TextColumn::make('updated_at')->dateTime('d.m.Y H:i')->sortable(),";
        $tableColsStr = implode("\n", $tableCols);

        $modelClass = '\\'.$this->modelClass($type);

        return <<<PHP
<?php

namespace App\Filament\Resources\\{$studly};

use App\Filament\Resources\\{$studly}\Pages\Create{$studly};
use App\Filament\Resources\\{$studly}\Pages\Edit{$studly};
use App\Filament\Resources\\{$studly}\Pages\List{$studly};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class {$studly}Resource extends Resource
{
    protected static ?string \$model = {$modelClass}::class;

    protected static string|\BackedEnum|null \$navigationIcon = '{$icon}';

    protected static string|\UnitEnum|null \$navigationGroup = 'Conteúdos';

    protected static ?string \$navigationLabel = '{$type->name}';

    protected static ?string \$recordTitleAttribute = '{$this->ownTitleAttr($type)}';

    public static function form(Schema \$schema): Schema
    {
        return \$schema->components([
            \Filament\Schemas\Components\Section::make()->columns(2)->schema([
{$form}
                \Filament\Forms\Components\TextInput::make('slug')->alphaDash(),
                \Filament\Forms\Components\Select::make('status')
                    ->options(['draft' => 'Rascunho', 'published' => 'Publicado'])
                    ->default('draft')
                    ->required(),
            ]),
        ]);
    }

    public static function table(Table \$table): Table
    {
        return \$table
            ->columns([
{$tableColsStr}
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => List{$studly}::route('/'),
            'create' => Create{$studly}::route('/create'),
            'edit' => Edit{$studly}::route('/{record}/edit'),
        ];
    }
}

PHP;
    }

    private function formInputFor(array $field): string
    {
        $name = Str::snake($field['name']);
        $label = $field['label'] ?? Str::headline($field['name']);
        $base = match ($field['type'] ?? 'text') {
            'textarea' => "\\Filament\\Forms\\Components\\Textarea::make('{$name}')->rows(3)",
            'richtext' => "\\Filament\\Forms\\Components\\RichEditor::make('{$name}')",
            'number' => "\\Filament\\Forms\\Components\\TextInput::make('{$name}')->numeric()",
            'boolean' => "\\Filament\\Forms\\Components\\Toggle::make('{$name}')",
            'date' => "\\Filament\\Forms\\Components\\DatePicker::make('{$name}')",
            'select' => "\\Filament\\Forms\\Components\\Select::make('{$name}')->options(".$this->phpArray($field['options'] ?? []).')',
            'media' => "\\Filament\\Forms\\Components\\FileUpload::make('{$name}')->image()->disk('public')->directory('cms')",
            'link' => "\\Filament\\Forms\\Components\\TextInput::make('{$name}')->url()",
            'menu' => "\\Filament\\Forms\\Components\\Select::make('{$name}')->options(\\App\\Models\\Cms\\Menu::query()->pluck('name', 'id'))",
            default => "\\Filament\\Forms\\Components\\TextInput::make('{$name}')",
        };

        $base .= "->label('{$label}')";

        if (! empty($field['required'])) {
            $base .= '->required()';
        }

        return $base;
    }

    private function relationInput(array $relation): string
    {
        $title = $this->targetTitleAttr($relation['target']);
        $label = $relation['label'] ?? Str::headline($relation['name']);

        if ($relation['type'] === 'belongsToMany') {
            $name = Str::camel($relation['name']);

            return "\\Filament\\Forms\\Components\\Select::make('{$name}')->relationship('{$name}', '{$title}')->multiple()->preload()->searchable()->label('{$label}')";
        }

        // belongsTo
        $name = Str::camel($relation['name']);

        return "\\Filament\\Forms\\Components\\Select::make('{$this->fkColumn($relation)}')->relationship('{$name}', '{$title}')->searchable()->preload()->label('{$label}')";
    }

    private function pageStub(ContentType $type, string $action, string $parent): string
    {
        $studly = $this->studly($type);
        $extra = match ($action) {
            'List' => "\n    protected function getHeaderActions(): array\n    {\n        return [\\Filament\\Actions\\CreateAction::make()];\n    }\n",
            'Edit' => "\n    protected function getHeaderActions(): array\n    {\n        return [\\Filament\\Actions\\DeleteAction::make()];\n    }\n",
            default => '',
        };

        return <<<PHP
<?php

namespace App\Filament\Resources\\{$studly}\Pages;

use App\Filament\Resources\\{$studly}\\{$studly}Resource;
use Filament\Resources\Pages\\{$parent};

class {$action}{$studly} extends {$parent}
{
    protected static string \$resource = {$studly}Resource::class;
{$extra}}

PHP;
    }

    // ---- helpers -----------------------------------------------------------

    private function belongsTo(ContentType $type): array
    {
        return array_values(array_filter($type->relationDefs(), fn ($r) => ($r['type'] ?? null) === 'belongsTo'));
    }

    private function belongsToMany(ContentType $type): array
    {
        return array_values(array_filter($type->relationDefs(), fn ($r) => ($r['type'] ?? null) === 'belongsToMany'));
    }

    private function listableFields(ContentType $type): array
    {
        $listable = array_values(array_filter($type->fields(), fn ($f) => ! empty($f['listable'])));

        return $listable !== [] ? $listable : array_slice($type->fields(), 0, 2);
    }

    private function fkColumn(array $relation): string
    {
        return Str::snake(Str::singular($relation['name'])).'_id';
    }

    private function targetTable(string $fqcn): string
    {
        $fqcn = '\\'.ltrim($fqcn, '\\');

        if (isset(self::CORE_TABLES[ltrim($fqcn, '\\')])) {
            return self::CORE_TABLES[ltrim($fqcn, '\\')]['table'];
        }

        return Str::snake(Str::pluralStudly(class_basename($fqcn)));
    }

    private function targetTitleAttr(string $fqcn): string
    {
        $key = ltrim($fqcn, '\\');

        if (isset(self::CORE_TABLES[$key])) {
            return self::CORE_TABLES[$key]['title'];
        }

        // Tipo gerado: primeiro campo de texto do seu blueprint, senão 'id'.
        $target = ContentType::query()
            ->get()
            ->first(fn (ContentType $t) => $this->modelClass($t) === $key);

        if ($target) {
            foreach ($target->fields() as $field) {
                if (in_array($field['type'] ?? '', ['text', 'textarea', 'string'], true)) {
                    return Str::snake($field['name']);
                }
            }
        }

        return 'id';
    }

    private function ownTitleAttr(ContentType $type): string
    {
        foreach ($type->fields() as $field) {
            if (in_array($field['type'] ?? '', ['text', 'textarea'], true)) {
                return Str::snake($field['name']);
            }
        }

        return 'slug';
    }

    private function phpArray(array $options): string
    {
        if ($options === []) {
            return '[]';
        }

        $assoc = array_is_list($options) ? array_combine($options, $options) : $options;
        $pairs = collect($assoc)->map(fn ($v, $k) => "'".addslashes((string) $k)."' => '".addslashes((string) $v)."'")->implode(', ');

        return '['.$pairs.']';
    }
}
