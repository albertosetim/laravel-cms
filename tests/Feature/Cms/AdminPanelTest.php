<?php

use App\Filament\Resources\Cms\ContentTypes\ContentTypeResource;
use App\Filament\Resources\Cms\Menus\MenuResource;
use App\Filament\Resources\Cms\Pages\PageResource;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsRole(string $role): User
{
    Role::findOrCreate($role);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    return $user;
}

it('admin abre o painel e os resources de estrutura e system', function () {
    actingAsRole('admin');

    $this->get(PageResource::getUrl('index'))->assertOk();
    $this->get(MenuResource::getUrl('index'))->assertOk();
    $this->get(MenuResource::getUrl('create'))->assertOk();
    $this->get(ContentTypeResource::getUrl('index'))->assertOk();
    $this->get(ContentTypeResource::getUrl('create'))->assertOk();
    $this->get(UserResource::getUrl('index'))->assertOk();
    $this->get(UserResource::getUrl('create'))->assertOk();
    $this->get(RoleResource::getUrl('index'))->assertOk();
    $this->get(GroupResource::getUrl('index'))->assertOk();
});

it('nega a editores o acesso a System (users/roles/groups/tipos)', function () {
    actingAsRole('editor');

    $this->get(UserResource::getUrl('index'))->assertForbidden();
    $this->get(RoleResource::getUrl('index'))->assertForbidden();
    $this->get(GroupResource::getUrl('index'))->assertForbidden();
    $this->get(ContentTypeResource::getUrl('index'))->assertForbidden();
});

it('permite a editores gerir paginas', function () {
    actingAsRole('editor');

    $this->get(PageResource::getUrl('index'))->assertOk();
});
