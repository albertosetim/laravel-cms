<?php

namespace App\Filament\Resources\Cms\Pages\Pages;

use App\Filament\Resources\Cms\Pages\PageResource;
use App\Filament\Resources\Cms\Pages\Pages\Concerns\HandlesPageBlocks;
use App\Models\Cms\Page;
use App\Services\Cms\PagePublisher;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePage extends CreateRecord
{
    use HandlesPageBlocks;

    protected static string $resource = PageResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $blocks = $this->builderStateToBlocks($data['blocks'] ?? []);
        unset($data['blocks']);

        /** @var Page $page */
        $page = static::getModel()::create($data);

        app(PagePublisher::class)->saveDraft($page, ['blocks' => $blocks], auth()->id());

        return $page;
    }
}
