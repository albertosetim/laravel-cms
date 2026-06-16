<?php

namespace App\Filament\Resources\Media;

use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Cms\MediaItem;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MediaResource extends Resource
{
    protected static ?string $model = MediaItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('Content');
    }

    public static function getNavigationLabel(): string
    {
        return __('Media');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(1)->schema([
                TextInput::make('title')
                    ->label(__('Title'))
                    ->required()
                    ->maxLength(255),
                SpatieMediaLibraryFileUpload::make('media')
                    ->label(__('Files'))
                    ->collection('media')
                    ->multiple()
                    ->reorderable()
                    ->downloadable()
                    ->openable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('media')
                    ->label(__('Preview'))
                    ->collection('media')
                    ->conversion('thumb')
                    ->circular()
                    ->stacked()
                    ->limit(3),
                TextColumn::make('title')->label(__('Title'))->searchable(),
                TextColumn::make('media_count')
                    ->label(__('Files'))
                    ->counts('media')
                    ->badge(),
                TextColumn::make('updated_at')->label(__('Updated'))->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'create' => CreateMedia::route('/create'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }
}
