<?php

namespace App\Filament\Livewire\Settings;

use App\Filament\Resources\Roles\RoleResource;
use Spatie\Permission\Models\Role;

class RolesManager extends ResourceTableManager
{
    protected function resource(): string
    {
        return RoleResource::class;
    }

    protected function model(): string
    {
        return Role::class;
    }
}
