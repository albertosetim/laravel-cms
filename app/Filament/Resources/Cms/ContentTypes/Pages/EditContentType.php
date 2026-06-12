<?php

namespace App\Filament\Resources\Cms\ContentTypes\Pages;

use App\Filament\Resources\Cms\ContentTypes\ContentTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContentType extends EditRecord
{
    protected static string $resource = ContentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
