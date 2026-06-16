<?php

use App\Filament\Resources\Cms\ContentTypes\ContentTypeResource;
use App\Filament\Resources\Media\MediaResource;
use App\Models\Cms\ContentType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'editor', 'developer'] as $role) {
        Role::findOrCreate($role);
    }
});

it('media fica no grupo Content e as ferramentas técnicas em Developer tools', function () {
    expect(MediaResource::getNavigationGroup())->toBe(__('Content'))
        ->and(ContentTypeResource::getNavigationGroup())->toBe(__('Developer tools'));
});

it('developer entra no painel e tem acesso total + dev tools (como admin)', function () {
    $dev = User::factory()->create();
    $dev->assignRole('developer');

    expect($dev->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and($dev->can('viewAny', ContentType::class))->toBeTrue()
        ->and($dev->can('viewSystemLogs'))->toBeTrue()
        ->and($dev->can('manageSettings'))->toBeTrue();
});

it('editor entra no painel mas não vê as dev tools', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    expect($editor->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and($editor->can('viewAny', ContentType::class))->toBeFalse()
        ->and($editor->can('viewSystemLogs'))->toBeFalse()
        ->and($editor->can('manageSettings'))->toBeFalse();
});

it('o painel renderiza para admin com o botão sticky de Settings (render hook)', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee(__('Settings'));
});
