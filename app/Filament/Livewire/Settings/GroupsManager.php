<?php

namespace App\Filament\Livewire\Settings;

use App\Filament\Resources\Groups\GroupResource;
use App\Models\Cms\Group;

class GroupsManager extends ResourceTableManager
{
    protected function resource(): string
    {
        return GroupResource::class;
    }

    protected function model(): string
    {
        return Group::class;
    }
}
