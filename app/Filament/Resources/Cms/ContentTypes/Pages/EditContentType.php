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

    /**
     * Se o tipo já foi gerado, uma edição ao blueprint/relações emite uma nova
     * migration de ALTER (criar/alterar/apagar colunas) e migra. O Model e o
     * Resource gerados ficam intactos (o dev é dono desses ficheiros).
     */
    protected function afterSave(): void
    {
        if ($this->getRecord()->generated) {
            ContentTypeResource::runAlter($this->getRecord());
        }
    }
}
