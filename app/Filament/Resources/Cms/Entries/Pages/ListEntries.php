<?php

namespace App\Filament\Resources\Cms\Entries\Pages;

use App\Filament\Resources\Cms\Entries\EntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEntries extends ListRecords
{
    protected static string $resource = EntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
