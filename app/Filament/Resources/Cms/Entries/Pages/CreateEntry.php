<?php

namespace App\Filament\Resources\Cms\Entries\Pages;

use App\Cms\Render\RichTextSanitizer;
use App\Filament\Resources\Cms\Entries\EntryResource;
use App\Models\Cms\ContentType;
use Filament\Resources\Pages\CreateRecord;

class CreateEntry extends CreateRecord
{
    protected static string $resource = EntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $fields = ContentType::query()->find($data['type_id'])?->fields() ?? [];
        $data['data'] = app(RichTextSanitizer::class)->sanitizeValues($data['data'] ?? [], $fields);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        $type = ContentType::query()->find($this->record->type_id);

        return EntryResource::getUrl('index', $type ? ['type' => $type->slug] : []);
    }
}
