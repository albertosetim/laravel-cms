<?php

namespace App\Filament\Resources\Blog;

use App\Filament\Resources\Blog\Pages\CreateBlog;
use App\Filament\Resources\Blog\Pages\EditBlog;
use App\Filament\Resources\Blog\Pages\ListBlog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BlogResource extends Resource
{
    protected static ?string $model = \App\Models\Blog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationGroup(): ?string
    {
        return __('Content');
    }

    public static function getNavigationLabel(): string
    {
        return __('Blog');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make()->columns(2)->schema([
                \Filament\Forms\Components\TextInput::make('title')->label(__('Title')),
                \Filament\Forms\Components\TextInput::make('slug')->alphaDash(),
                \Filament\Forms\Components\Select::make('status')
                    ->options(['draft' => __('Draft'), 'published' => __('Published')])
                    ->default('draft')
                    ->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('title')->searchable(),
                \Filament\Tables\Columns\TextColumn::make('status')->badge(),
                \Filament\Tables\Columns\TextColumn::make('updated_at')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlog::route('/'),
            'create' => CreateBlog::route('/create'),
            'edit' => EditBlog::route('/{record}/edit'),
        ];
    }
}
