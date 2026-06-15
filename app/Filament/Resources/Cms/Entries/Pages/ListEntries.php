<?php

namespace App\Filament\Resources\Cms\Entries\Pages;

use App\Filament\Resources\Cms\Entries\EntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEntries extends ListRecords
{
    protected static string $resource = EntryResource::class;

    public function getTitle(): string
    {
        return EntryResource::activeType()?->name ?? 'Conteúdos';
    }

    protected function getHeaderActions(): array
    {
        $type = EntryResource::activeType();

        return [
            CreateAction::make()
                ->label($type ? 'Novo '.$type->name : 'Novo conteúdo')
                // Leva o tipo ativo para o form de criação.
                ->url(fn () => EntryResource::getUrl('create', $type ? ['type' => $type->slug] : [])),
        ];
    }
}
