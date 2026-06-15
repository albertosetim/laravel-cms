<?php

namespace App\Filament\Resources\Cms\Menus;

use App\Filament\Resources\Cms\Menus\Pages\CreateMenu;
use App\Filament\Resources\Cms\Menus\Pages\EditMenu;
use App\Filament\Resources\Cms\Menus\Pages\ListMenus;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Menu builder estilo Shopify: cada menu tem nome + slug e uma árvore de itens
 * (2 níveis). Coloca-se numa página através do bloco "Menu". Itens são um
 * documento coeso em jsonb (G4).
 */
class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?string $navigationLabel = 'Menus';

    protected static string|\UnitEnum|null $navigationGroup = 'Estrutura';

    protected static ?string $modelLabel = 'menu';

    protected static ?string $pluralModelLabel = 'menus';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set, string $operation) => $operation === 'create'
                        ? $set('slug', str($state)->slug()->toString())
                        : null),
                TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
            ]),
            Section::make('Itens')->schema([
                self::itemsRepeater('items')
                    ->label('Itens do menu'),
            ]),
        ]);
    }

    /** Repeater de itens com um nível de filhos (sub-menu). */
    private static function itemsRepeater(string $name, bool $allowChildren = true): Repeater
    {
        $schema = self::itemFields();

        if ($allowChildren) {
            $schema[] = self::itemsRepeater('children', allowChildren: false)
                ->label('Sub-itens')
                ->columnSpanFull();
        }

        return Repeater::make($name)
            ->schema($schema)
            ->columns(3)
            ->reorderable()
            ->collapsible()
            ->cloneable()
            ->itemLabel(fn (array $state) => $state['label'] ?? null)
            ->addActionLabel('Adicionar item')
            ->defaultItems(0);
    }

    private static function itemFields(): array
    {
        return [
            TextInput::make('label')->label('Texto')->required(),
            Select::make('type')
                ->label('Destino')
                ->options(['page' => 'Página', 'url' => 'URL externo'])
                ->default('page')
                ->live()
                ->required(),
            Select::make('page_id')
                ->label('Página')
                ->options(fn () => Page::query()->orderBy('name')->get()->mapWithKeys(
                    fn (Page $p) => [$p->id => $p->name.' ('.$p->locale.')'],
                ))
                ->searchable()
                ->visible(fn (Get $get) => $get('type') === 'page')
                ->required(fn (Get $get) => $get('type') === 'page'),
            TextInput::make('url')
                ->label('URL')
                ->url()
                ->visible(fn (Get $get) => $get('type') === 'url')
                ->required(fn (Get $get) => $get('type') === 'url'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('slug')->badge(),
                TextColumn::make('items')
                    ->label('Itens')
                    ->state(fn (Menu $record) => count($record->items ?? [])),
                TextColumn::make('updated_at')->label('Atualizado')->dateTime('d.m.Y H:i')->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenus::route('/'),
            'create' => CreateMenu::route('/create'),
            'edit' => EditMenu::route('/{record}/edit'),
        ];
    }
}
