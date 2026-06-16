<?php

namespace App\Filament\Resources\Cms\ContentTypes;

use App\Cms\Generator\TypeGenerator;
use App\Filament\Resources\Cms\ContentTypes\Pages\CreateContentType;
use App\Filament\Resources\Cms\ContentTypes\Pages\EditContentType;
use App\Filament\Resources\Cms\ContentTypes\Pages\ListContentTypes;
use App\Models\Cms\ContentType;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

/**
 * Designer de models: o admin define campos + relações; o botão "Gerar código"
 * (ou `cms:make:type`) emite Model + Migration + FilamentResource reais.
 * Por decisão do utilizador, a geração corre a partir do admin em qualquer
 * ambiente (o blueprint original proibia codegen em runtime — G1).
 */
class ContentTypeResource extends Resource
{
    protected static ?string $model = ContentType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('System');
    }

    public static function getNavigationLabel(): string
    {
        return __('Content types');
    }

    public static function getModelLabel(): string
    {
        return __('content type');
    }

    public static function getPluralModelLabel(): string
    {
        return __('content types');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set, string $operation) => $operation === 'create'
                        ? $set('slug', str($state)->slug()->toString())
                        : null),
                TextInput::make('slug')
                    ->required()
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit')
                    ->helperText(__('Becomes the name of the generated table/model.')),
                TextInput::make('icon')
                    ->label(__('Icon (heroicon)'))
                    ->placeholder('heroicon-o-users'),
            ]),
            Section::make(__('Fields'))
                ->description(__('Each field becomes a typed column in the generated table.'))
                ->schema([
                    Repeater::make('blueprint.fields')
                        ->hiddenLabel()
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['label'] ?? $state['name'] ?? null)
                        ->addActionLabel(__('Add field'))
                        ->schema([
                            TextInput::make('name')->required()->alphaDash()->label(__('Technical name')),
                            TextInput::make('label')->label(__('Label')),
                            Select::make('type')
                                ->required()
                                ->default('text')
                                ->live()
                                ->options([
                                    'text' => __('Text'),
                                    'textarea' => __('Long text'),
                                    'richtext' => __('Rich text'),
                                    'number' => __('Number'),
                                    'boolean' => __('Yes/No'),
                                    'date' => __('Date'),
                                    'select' => __('Selection'),
                                    'media' => __('Image'),
                                    'link' => __('Link (URL)'),
                                    'menu' => __('Menu'),
                                ]),
                            Toggle::make('required')->label(__('Required'))->inline(false),
                            TagsInput::make('options')
                                ->label(__('Options'))
                                ->visible(fn (Get $get) => $get('type') === 'select'),
                            Toggle::make('multiple')
                                ->label(__('Multiple files'))
                                ->inline(false)
                                ->visible(fn (Get $get) => $get('type') === 'media'),
                            Toggle::make('listable')
                                ->label(__('Show in listing (indexed column)'))
                                ->inline(false),
                        ])
                        ->columns(3),
                ]),
            Section::make(__('Relations'))
                ->description(__('Relations between tables, generated in the Model, the Migration (FK/pivot) and the Resource.'))
                ->schema([
                    Repeater::make('relation_defs')
                        ->hiddenLabel()
                        ->reorderable(false)
                        ->collapsible()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state) => ($state['name'] ?? '').' ('.($state['type'] ?? '').')')
                        ->addActionLabel(__('Add relation'))
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->alphaDash()
                                ->label(__('Relation name'))
                                ->helperText(__('e.g.: category, tags, comments')),
                            Select::make('type')
                                ->required()
                                ->default('belongsTo')
                                ->options([
                                    'belongsTo' => __('Belongs to (belongsTo) — FK on this table'),
                                    'belongsToMany' => __('Many-to-many (belongsToMany) — pivot table'),
                                    'hasMany' => __('Has many (hasMany) — inverse of a belongsTo'),
                                ]),
                            Select::make('target')
                                ->required()
                                ->label(__('Target table/model'))
                                ->options(fn () => self::relationTargets())
                                ->searchable(),
                        ])
                        ->columns(3),
                ]),
        ]);
    }

    /** Alvos possíveis para relações: outros tipos + models do core. */
    public static function relationTargets(): array
    {
        $generator = app(TypeGenerator::class);

        $types = ContentType::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ContentType $t) => [$generator->modelClass($t) => $t->name.' ('.__('type').')'])
            ->all();

        $core = [
            Page::class => __('Pages (core)'),
            Menu::class => __('Menus (core)'),
            User::class => __('Users (core)'),
        ];

        return $types + $core;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable(),
                TextColumn::make('slug')->badge(),
                TextColumn::make('fields_count')
                    ->label(__('Fields'))
                    ->state(fn (ContentType $r) => count($r->fields())),
                IconColumn::make('generated')->label(__('Generated'))->boolean(),
                TextColumn::make('updated_at')->label(__('Updated'))->dateTime('d.m.Y H:i'),
            ])
            ->recordActions([
                Action::make('generate')
                    ->label(__('Generate code'))
                    ->icon('heroicon-o-cpu-chip')
                    ->color('success')
                    ->visible(fn (ContentType $record) => ! $record->generated)
                    ->requiresConfirmation()
                    ->modalDescription(__('Generates real Model + Migration + Resource and runs migrate. The files are committed to git.'))
                    ->action(fn (ContentType $record) => self::runGenerate($record)),
                EditAction::make(),
            ]);
    }

    /** Gera Model+Migration+Resource e migra. Usado pelo botão e pelo create. */
    public static function runGenerate(ContentType $record): void
    {
        try {
            $written = app(TypeGenerator::class)->generate($record);
        } catch (\RuntimeException $e) {
            Notification::make()->title(__('Could not generate'))->body($e->getMessage())->danger()->send();

            return;
        }

        Artisan::call('migrate', ['--force' => true]);

        Notification::make()
            ->title($record->name.' '.__('generated'))
            ->body(count($written).' '.__('file(s) written and migrated. Now appears under "Content" in the menu.'))
            ->success()
            ->send();
    }

    /**
     * Editar um tipo já gerado: calcula o diff e emite uma migration de ALTER
     * (criar/alterar/remover colunas e relações), depois migra. Não toca no
     * Model nem no Resource — o dev atualiza fillable/casts/form à mão.
     */
    public static function runAlter(ContentType $record): void
    {
        $written = app(TypeGenerator::class)->regenerate($record);

        if ($written === []) {
            Notification::make()
                ->title(__('No schema changes'))
                ->body(__('The edit changed no column or relation.'))
                ->info()
                ->send();

            return;
        }

        Artisan::call('migrate', ['--force' => true]);

        Notification::make()
            ->title($record->name.' '.__('updated'))
            ->body(count($written).' '.__('migration(s) written and migrated. Update the Model (fillable/casts) and the Resource form by hand — and, for new image fields, add HasMedia + SpatieMediaLibraryFileUpload.'))
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentTypes::route('/'),
            'create' => CreateContentType::route('/create'),
            'edit' => EditContentType::route('/{record}/edit'),
        ];
    }
}
