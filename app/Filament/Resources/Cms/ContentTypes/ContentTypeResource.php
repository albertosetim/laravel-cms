<?php

namespace App\Filament\Resources\Cms\ContentTypes;

use App\Filament\Resources\Cms\ContentTypes\Pages\CreateContentType;
use App\Filament\Resources\Cms\ContentTypes\Pages\EditContentType;
use App\Filament\Resources\Cms\ContentTypes\Pages\ListContentTypes;
use App\Models\Cms\ContentType;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * O admin cria TIPOS de conteúdo — que são dados (blueprint jsonb), nunca
 * código (G7). Os entries ganham um CRUD dinâmico no EntryResource.
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
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', str($state)->slug()->toString())),
                TextInput::make('slug')
                    ->required()
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit'),
                TextInput::make('icon')
                    ->label('Ícone (heroicon)')
                    ->placeholder('heroicon-o-users'),
            ]),
            Section::make('Campos')
                ->description('A forma dos entries deste tipo. Campos filtráveis usam o índice GIN do jsonb — sem DDL, sem EAV.')
                ->schema([
                    Repeater::make('blueprint.fields')
                        ->hiddenLabel()
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['label'] ?? $state['name'] ?? null)
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
                                    'link' => 'Link',
                                    'repeater' => 'Lista (repeater)',
                                ]),
                            Toggle::make('required')->label('Obrigatório')->inline(false),
                            TagsInput::make('options')
                                ->label('Opções')
                                ->visible(fn (Get $get) => $get('type') === 'select'),
                            Toggle::make('listable')
                                ->label('Mostrar na listagem')
                                ->inline(false),
                            Repeater::make('fields')
                                ->label('Subcampos (um nível)')
                                ->visible(fn (Get $get) => $get('type') === 'repeater')
                                ->schema([
                                    TextInput::make('name')->required()->alphaDash()->label('Nome técnico'),
                                    TextInput::make('label')->label('Label'),
                                    Select::make('type')
                                        ->required()
                                        ->default('text')
                                        ->options([
                                            'text' => 'Texto',
                                            'textarea' => 'Texto longo',
                                            'number' => 'Número',
                                            'boolean' => 'Sim/Não',
                                            'date' => 'Data',
                                            'media' => 'Imagem',
                                        ]),
                                ])
                                ->columns(3)
                                ->columnSpanFull(),
                        ])
                        ->columns(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('slug'),
                TextColumn::make('entries_count')->label('Entries')->counts('entries'),
                IconColumn::make('promoted')->label('Promovido')->boolean(),
                TextColumn::make('updated_at')->label('Atualizado')->dateTime('d.m.Y H:i'),
            ]);
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
