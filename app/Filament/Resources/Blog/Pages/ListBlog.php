<?php

namespace App\Filament\Resources\Blog\Pages;

use App\Filament\Resources\Blog\BlogResource;
use Filament\Resources\Pages\ListRecords;

class ListBlog extends ListRecords
{
    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
