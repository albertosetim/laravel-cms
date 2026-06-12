<?php

namespace App\Filament\Resources\Cms\Entries\Pages;

use App\Cms\Render\RichTextSanitizer;
use App\Filament\Resources\Cms\Entries\EntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEntry extends EditRecord
{
    protected static string $resource = EntryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $fields = $this->getRecord()->type?->fields() ?? [];
        $data['data'] = app(RichTextSanitizer::class)->sanitizeValues($data['data'] ?? [], $fields);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
