<?php

namespace App\Filament\Resources\Cms\ContentTypes;

use App\Cms\Generator\TypeGenerator;
use App\Filament\Resources\Cms\ContentTypes\Pages\CreateContentType;
use App\Filament\Resources\Cms\ContentTypes\Pages\EditContentType;
use App\Filament\Resources\Cms\ContentTypes\Pages\ListContentTypes;
use App\Models\Cms\ContentType;
use BackedEnum;
use Filament\Actions\Action;
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

    protected static ?string $navigationLabel = 'Tipos de conteúdo';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $modelLabel = 'tipo de conteúdo';

    protected static ?string $pluralModelLabel = 'tipos de conteúdo';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextInput::make('name')
                    ->label('Nome')
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
                    ->helperText('Vira o nome da tabela/model gerado.'),
                TextInput::make('icon')
                    ->label('Ícone (heroicon)')
                    ->placeholder('heroicon-o-users'),
            ]),
            Section::make('Campos')
                ->description('Cada campo vira uma coluna tipada na tabela gerada.')
                ->schema([
                    Repeater::make('blueprint.fields')
                        ->hiddenLabel()
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['label'] ?? $state['name'] ?? null)
                        ->addActionLabel('Adicionar campo')
                        ->schema([
                            TextInput::make('name')->required()->alphaDash()->label('Nome técnico'),
                            TextInput::make('label')->label('Label'),
                            Select::make('type')
                                ->required()
                                ->default('text')
                                ->live()
                                ->options([
                                    'text' => 'Texto',
                                    'textarea' => 'Texto longo',
                                    'richtext' => 'Rich text',
                                    'number' => 'Número',
                                    'boolean' => 'Sim/Não',
                                    'date' => 'Data',
                                    'select' => 'Seleção',
                                    'media' => 'Imagem',
                                    'link' => 'Link (URL)',
                                    'menu' => 'Menu',
                                ]),
                            Toggle::make('required')->label('Obrigatório')->inline(false),
                            TagsInput::make('options')
                                ->label('Opções')
                                ->visible(fn (Get $get) => $get('type') === 'select'),
                            Toggle::make('listable')
                                ->label('Mostrar na listagem (coluna indexada)')
                                ->inline(false),
                        ])
                        ->columns(3),
                ]),
            Section::make('Relações')
                ->description('Relações entre tabelas, geradas no Model, na Migration (FK/pivot) e no Resource.')
                ->schema([
                    Repeater::make('relation_defs')
                        ->hiddenLabel()
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn (array $state) => ($state['name'] ?? '').' ('.($state['type'] ?? '').')')
                        ->addActionLabel('Adicionar relação')
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->alphaDash()
                                ->label('Nome da relação')
                                ->helperText('ex.: category, tags, comments'),
                            Select::make('type')
                                ->required()
                                ->default('belongsTo')
                                ->options([
                                    'belongsTo' => 'Pertence a (belongsTo) — FK nesta tabela',
                                    'belongsToMany' => 'Muitos-para-muitos (belongsToMany) — tabela pivot',
                                    'hasMany' => 'Tem muitos (hasMany) — inverso de um belongsTo',
                                ]),
                            Select::make('target')
                                ->required()
                                ->label('Tabela/model alvo')
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
            ->mapWithKeys(fn (ContentType $t) => [$generator->modelClass($t) => $t->name.' (tipo)'])
            ->all();

        $core = [
            \App\Models\Cms\Page::class => 'Páginas (core)',
            \App\Models\Cms\Menu::class => 'Menus (core)',
            \App\Models\User::class => 'Utilizadores (core)',
        ];

        return $types + $core;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('slug')->badge(),
                TextColumn::make('fields_count')
                    ->label('Campos')
                    ->state(fn (ContentType $r) => count($r->fields())),
                IconColumn::make('generated')->label('Gerado')->boolean(),
                TextColumn::make('updated_at')->label('Atualizado')->dateTime('d.m.Y H:i'),
            ])
            ->recordActions([
                Action::make('generate')
                    ->label('Gerar código')
                    ->icon('heroicon-o-cpu-chip')
                    ->color('success')
                    ->visible(fn (ContentType $record) => ! $record->generated)
                    ->requiresConfirmation()
                    ->modalDescription('Gera Model + Migration + Resource reais e corre migrate. Os ficheiros entram no git.')
                    ->action(fn (ContentType $record) => self::runGenerate($record)),
                \Filament\Actions\EditAction::make(),
            ]);
    }

    private static function runGenerate(ContentType $record): void
    {
        try {
            $written = app(TypeGenerator::class)->generate($record);
        } catch (\RuntimeException $e) {
            Notification::make()->title('Não foi possível gerar')->body($e->getMessage())->danger()->send();

            return;
        }

        Artisan::call('migrate', ['--force' => true]);

        Notification::make()
            ->title($record->name.' gerado')
            ->body(count($written).' ficheiro(s) escritos e migrados. Recarrega para ver o novo item no menu.')
            ->success()
            ->persistent()
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
