<?php

namespace App\Filament\Resources\Cms\ContentTypes\Pages;

use App\Filament\Resources\Cms\ContentTypes\ContentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContentTypes extends ListRecords
{
    protected static string $resource = ContentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
