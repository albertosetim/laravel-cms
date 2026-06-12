<?php

namespace App\Filament\Resources\Cms\Pages\Tables;

use App\Models\Cms\Page;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Página')
                    ->searchable(),
                TextColumn::make('path')
                    ->label('Path')
                    ->state(fn (Page $record) => '/'.$record->locale.'/'.$record->path()),
                TextColumn::make('locale')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => $state === Page::STATUS_PUBLISHED ? 'success' : 'gray'),
                IconColumn::make('show_in_menu')->label('Menu')->boolean(),
                TextColumn::make('updated_at')->label('Atualizada')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('position')
            ->modifyQueryUsing(fn ($query) => $query->orderBy('parent_id', 'asc')->orderBy('position'))
            ->filters([
                SelectFilter::make('locale')
                    ->options(fn () => array_combine(config('cms.locales'), config('cms.locales'))),
                SelectFilter::make('status')
                    ->options([
                        Page::STATUS_DRAFT => 'Rascunho',
                        Page::STATUS_PUBLISHED => 'Publicada',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
