<?php

namespace App\Filament\Resources\Cms\ContentTypes\Pages;

use App\Filament\Resources\Cms\ContentTypes\ContentTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContentType extends CreateRecord
{
    protected static string $resource = ContentTypeResource::class;
}
