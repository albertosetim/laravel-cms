<?php

namespace App\Filament\Resources\Cms\ContentTypes\Pages;

use App\Filament\Resources\Cms\ContentTypes\ContentTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContentType extends CreateRecord
{
    protected static string $resource = ContentTypeResource::class;

    /**
     * Criar um tipo gera logo o Model + Migration + Resource — assim o tipo
     * aparece imediatamente sob "Conteúdos" no menu lateral (o resource gerado
     * auto-regista-se nesse grupo). Se a geração falhar, o tipo fica criado e o
     * botão "Gerar código" continua disponível.
     */
    protected function afterCreate(): void
    {
        ContentTypeResource::runGenerate($this->getRecord());
    }
}
