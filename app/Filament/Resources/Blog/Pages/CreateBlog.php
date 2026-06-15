<?php

namespace App\Filament\Resources\Blog\Pages;

use App\Filament\Resources\Blog\BlogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlog extends CreateRecord
{
    protected static string $resource = BlogResource::class;
}
