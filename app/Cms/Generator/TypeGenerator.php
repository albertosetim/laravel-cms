<?php

namespace App\Cms\Generator;

use App\Models\Cms\ContentType;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\User;
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
        Page::class => ['table' => 'cms_pages', 'title' => 'name'],
        Menu::class => ['table' => 'cms_menus', 'title' => 'name'],
        User::class => ['table' => 'users', 'title' => 'name'],
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
        $this->snapshot($type);

        return $written;
    }

    /**
     * Reage a uma edição de um tipo já gerado: calcula o diff contra o snapshot
     * e emite uma migration de ALTER (+ migrations de pivot para belongsToMany).
     * Por decisão do utilizador, NÃO toca no Model nem no Resource — o dev é dono
     * desses ficheiros e atualiza fillable/casts/form à mão.
     *
     * @return array<int, string> paths escritos (vazio se não houver mudanças de schema)
     */
    public function regenerate(ContentType $type): array
    {
        $differ = new SchemaDiffer;
        $fieldDiff = $differ->diffFields($type->generatedFields(), $type->fields());
        $relationDiff = $differ->diffRelations($type->generatedRelationDefs(), $type->relationDefs());

        if ($differ->isEmpty($fieldDiff, $relationDiff)) {
            $this->snapshot($type);

            return [];
        }

        $belongsToAdded = array_values(array_filter($relationDiff['added'], fn ($r) => ($r['type'] ?? null) === 'belongsTo'));
        $belongsToDropped = array_values(array_filter($relationDiff['dropped'], fn ($r) => ($r['type'] ?? null) === 'belongsTo'));
        $pivotAdded = array_values(array_filter($relationDiff['added'], fn ($r) => ($r['type'] ?? null) === 'belongsToMany'));
        $pivotDropped = array_values(array_filter($relationDiff['dropped'], fn ($r) => ($r['type'] ?? null) === 'belongsToMany'));

        $now = Carbon::now();
        $written = [];

        $columnChanges = $fieldDiff['added'] || $fieldDiff['dropped'] || $fieldDiff['changed']
            || $belongsToAdded || $belongsToDropped;

        if ($columnChanges) {
            $written[] = $this->put(
                $this->alterMigrationPath($type, $now),
                $this->alterMigrationStub($this->table($type), $fieldDiff, $belongsToAdded, $belongsToDropped),
            );
        }

        $i = 0;
        foreach ($pivotAdded as $relation) {
            $path = $this->pivotMigrationPath($type, $relation, $now->copy()->addSeconds(++$i));
            $written[] = $this->put($path, $this->pivotMigrationStub($type, $relation));
        }
        foreach ($pivotDropped as $relation) {
            $path = $this->dropPivotMigrationPath($type, $relation, $now->copy()->addSeconds(++$i));
            $written[] = $this->put($path, $this->dropPivotMigrationStub($type, $relation));
        }

        $this->snapshot($type);

        return $written;
    }

    /** Guarda o estado atual como referência para o próximo diff. */
    private function snapshot(ContentType $type): void
    {
        $type->forceFill([
            'generated_blueprint' => $type->blueprint,
            'generated_relation_defs' => $type->relation_defs,
        ])->save();
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
            $spec = SchemaDiffer::columnSpec($field);
            if ($spec === null) {
                continue; // media: gerido pelo Spatie, sem coluna própria
            }
            $lines[] = '            '.$this->renderColumn($spec);
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

    /**
     * Renderiza uma linha de coluna a partir de um spec do SchemaDiffer.
     * Em modo $change não emite ->index() (o índice não faz parte do change()).
     *
     * @param  array{name: string, method: string, nullable: bool, indexed: bool}  $spec
     */
    private function renderColumn(array $spec, bool $change = false): string
    {
        $line = '$table->'.$spec['method'].'(\''.$spec['name'].'\')';

        if ($spec['nullable']) {
            $line .= '->nullable()';
        }

        if ($spec['indexed'] && ! $change) {
            $line .= '->index()';
        }

        return $line.($change ? '->change();' : ';');
    }

    // ---- Migration de ALTER (edição de um tipo já gerado) ------------------

    private function alterMigrationPath(ContentType $type, Carbon $now): string
    {
        return database_path('migrations/'.$now->format('Y_m_d_His').'_update_'.$this->table($type).'_table.php');
    }

    /**
     * @param  array{added: list<array>, dropped: list<array>, changed: list<array{from: array, to: array}>}  $fieldDiff
     * @param  list<array>  $belongsToAdded
     * @param  list<array>  $belongsToDropped
     */
    private function alterMigrationStub(string $table, array $fieldDiff, array $belongsToAdded, array $belongsToDropped): string
    {
        $up = [];
        $down = [];
        $foreignKeys = '';

        foreach ($fieldDiff['added'] as $spec) {
            $up[] = $this->renderColumn($spec);
            $down[] = '$table->dropColumn(\''.$spec['name'].'\');';
        }

        foreach ($belongsToAdded as $relation) {
            $fk = $this->fkColumn($relation);
            $up[] = '$table->unsignedBigInteger(\''.$fk.'\')->nullable()->index();';
            $down[] = '$table->dropColumn(\''.$fk.'\');';

            $targetTable = $this->targetTable($relation['target']);
            $foreignKeys .= <<<PHP

        if (Schema::hasTable('{$targetTable}')) {
            Schema::table('{$table}', function (Blueprint \$table) {
                \$table->foreign('{$fk}')->references('id')->on('{$targetTable}')->nullOnDelete();
            });
        }
PHP;
        }

        foreach ($fieldDiff['changed'] as $change) {
            $up[] = $this->renderColumn($change['to'], change: true);
            $down[] = $this->renderColumn($change['from'], change: true);
        }

        // Postgres remove automaticamente a FK ao dropar a coluna que a contém.
        foreach ($belongsToDropped as $relation) {
            $fk = $this->fkColumn($relation);
            $up[] = '$table->dropColumn(\''.$fk.'\');';
            $down[] = '$table->unsignedBigInteger(\''.$fk.'\')->nullable()->index();';
        }

        foreach ($fieldDiff['dropped'] as $spec) {
            $up[] = '$table->dropColumn(\''.$spec['name'].'\');';
            $down[] = $this->renderColumn($spec);
        }

        $upCols = implode("\n", array_map(fn ($l) => '            '.$l, $up));
        $downCols = implode("\n", array_map(fn ($l) => '            '.$l, $down));

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
{$upCols}
        });
{$foreignKeys}
    }

    public function down(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
{$downCols}
        });
    }
};

PHP;
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

    private function dropPivotMigrationPath(ContentType $type, array $relation, Carbon $now): string
    {
        return database_path('migrations/'.$now->format('Y_m_d_His').'_drop_'.$this->pivotTable($type, $relation).'_table.php');
    }

    /** Espelho de pivotMigrationStub: dropa no up(), recria no down(). */
    private function dropPivotMigrationStub(ContentType $type, array $relation): string
    {
        $pivot = $this->pivotTable($type, $relation);
        $thisCol = Str::snake(Str::singular($this->studly($type))).'_id';
        $targetCol = Str::snake(Str::singular(class_basename($relation['target']))).'_id';

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('{$pivot}');
    }

    public function down(): void
    {
        Schema::create('{$pivot}', function (Blueprint \$table) {
            \$table->unsignedBigInteger('{$thisCol}');
            \$table->unsignedBigInteger('{$targetCol}');
            \$table->primary(['{$thisCol}', '{$targetCol}']);
        });
    }
};

PHP;
    }

    // ---- Model -------------------------------------------------------------

    private function modelStub(ContentType $type): string
    {
        $studly = $this->studly($type);

        // Campos media não têm coluna — são geridos pelo Spatie (tabela morph).
        $fillable = collect($type->fields())
            ->reject(fn (array $f) => ($f['type'] ?? '') === 'media')
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

        $bodyMethods = collect($type->relationDefs())
            ->map(fn (array $r) => $this->relationMethod($r))
            ->filter()
            ->all();

        $implements = '';
        $mediaTrait = '';
        if (($media = $this->mediaFields($type)) !== []) {
            $implements = ' implements \Spatie\MediaLibrary\HasMedia';
            $mediaTrait = "\n    use \Spatie\MediaLibrary\InteractsWithMedia;";
            $bodyMethods[] = $this->mediaMethods($media);
        }

        $methods = implode("\n\n", $bodyMethods);

        return <<<PHP
<?php

namespace App\Models;

use App\Models\Concerns\HasSettings;
use Illuminate\Database\Eloquent\Model;

class {$studly} extends Model{$implements}
{
    use HasSettings;{$mediaTrait}

    protected \$fillable = [{$fillableStr}];

    protected function casts(): array
    {
        return [
{$casts}
        ];
    }

{$methods}
}

PHP;
    }

    /** Campos do tipo cujo type é 'media'. */
    private function mediaFields(ContentType $type): array
    {
        return array_values(array_filter($type->fields(), fn ($f) => ($f['type'] ?? '') === 'media'));
    }

    /** registerMediaCollections (uma collection por campo) + conversão thumb. */
    private function mediaMethods(array $mediaFields): string
    {
        $collections = collect($mediaFields)->map(function (array $field) {
            $name = Str::snake($field['name']);
            $single = empty($field['multiple']) ? '->singleFile()' : '';

            return "        \$this->addMediaCollection('{$name}'){$single};";
        })->implode("\n");

        return <<<PHP
    public function registerMediaCollections(): void
    {
{$collections}
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media \$media = null): void
    {
        \$this->addMediaConversion('thumb')->width(200)->height(200)->nonQueued();
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
            $tableCols[] = ($field['type'] ?? '') === 'media'
                ? "                \\Filament\\Tables\\Columns\\SpatieMediaLibraryImageColumn::make('{$name}')->collection('{$name}')->conversion('thumb')->circular(),"
                : "                \\Filament\\Tables\\Columns\\TextColumn::make('{$name}')->searchable(),";
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

    protected static ?string \$navigationLabel = '{$type->name}';

    protected static ?string \$recordTitleAttribute = '{$this->ownTitleAttr($type)}';

    public static function getNavigationGroup(): ?string
    {
        return __('Content');
    }

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
            'media' => "\\Filament\\Forms\\Components\\SpatieMediaLibraryFileUpload::make('{$name}')->collection('{$name}')->image()".(empty($field['multiple']) ? '->singleFile()' : '->multiple()->reorderable()'),
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
            'List' => "\n    protected function getHeaderActions(): array\n    {\n        return [\n            \\App\\Filament\\Actions\\TypeSettingsAction::make()->settingsModel(static::getResource()::getModel()),\n            \\Filament\\Actions\\CreateAction::make(),\n        ];\n    }\n",
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
