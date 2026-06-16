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

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('Menus');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Structure');
    }

    public static function getModelLabel(): string
    {
        return __('menu');
    }

    public static function getPluralModelLabel(): string
    {
        return __('menus');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set, string $operation) => $operation === 'create'
                        ? $set('slug', str($state)->slug()->toString())
                        : null),
                TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
            ]),
            Section::make(__('Items'))->schema([
                self::itemsRepeater('items')
                    ->label(__('Menu items')),
            ]),
        ]);
    }

    /** Repeater de itens com um nível de filhos (sub-menu). */
    private static function itemsRepeater(string $name, bool $allowChildren = true): Repeater
    {
        $schema = self::itemFields();

        if ($allowChildren) {
            $schema[] = self::itemsRepeater('children', allowChildren: false)
                ->label(__('Sub-items'))
                ->columnSpanFull();
        }

        return Repeater::make($name)
            ->schema($schema)
            ->columns(3)
            ->reorderable()
            ->collapsible()
            ->cloneable()
            ->itemLabel(fn (array $state) => $state['label'] ?? null)
            ->addActionLabel(__('Add item'))
            ->defaultItems(0);
    }

    private static function itemFields(): array
    {
        return [
            TextInput::make('label')->label(__('Text'))->required(),
            Select::make('type')
                ->label(__('Destination'))
                ->options(['page' => __('Page'), 'url' => __('External URL')])
                ->default('page')
                ->live()
                ->required(),
            Select::make('page_id')
                ->label(__('Page'))
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
                TextColumn::make('name')->label(__('Name'))->searchable(),
                TextColumn::make('slug')->badge(),
                TextColumn::make('items')
                    ->label(__('Items'))
                    ->state(fn (Menu $record) => count($record->items ?? [])),
                TextColumn::make('updated_at')->label(__('Updated'))->dateTime('d.m.Y H:i')->sortable(),
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
