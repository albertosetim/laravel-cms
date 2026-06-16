<?php

use App\Filament\Livewire\Settings\GroupsManager;
use App\Filament\Livewire\Settings\RolesManager;
use App\Filament\Livewire\SettingsModal;
use App\Models\Cms\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'editor', 'developer'] as $role) {
        Role::findOrCreate($role);
    }
});

it('o RolesManager lista roles e o CreateAction reutiliza o form da resource', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)->test(RolesManager::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Role::all())
        ->callTableAction('create', data: ['name' => 'reviewer', 'guard_name' => 'web'])
        ->assertHasNoTableActionErrors();

    expect(Role::where('name', 'reviewer')->exists())->toBeTrue();
});

it('o GroupsManager cria grupos via modal', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)->test(GroupsManager::class)
        ->callTableAction('create', data: ['name' => 'Marketing', 'slug' => 'marketing'])
        ->assertHasNoTableActionErrors();

    expect(Group::where('slug', 'marketing')->exists())->toBeTrue();
});

it('o modal de Settings embute o manager ao escolher Permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)->test(SettingsModal::class)
        ->set('activeCategory', 'permissions')
        ->assertOk()
        ->assertSeeLivewire(RolesManager::class);
});

it('os managers negam acesso a não-admins/devs', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    Livewire::actingAs($editor)->test(RolesManager::class)->assertForbidden();
});
