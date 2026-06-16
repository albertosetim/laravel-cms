<?php

use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('admin');
});

it('o painel usa o locale default do site quando o user não escolheu', function () {
    $admin = User::factory()->create(['locale' => null]);
    $admin->assignRole('admin');

    expect($admin->preferredLocale())->toBe(config('cms.default_locale'));

    $this->actingAs($admin)->get(Dashboard::getUrl())->assertOk();

    expect(app()->getLocale())->toBe(config('cms.default_locale'));
});

it('o painel usa o idioma escolhido pelo user autenticado', function () {
    $admin = User::factory()->create(['locale' => 'en']);
    $admin->assignRole('admin');

    expect($admin->preferredLocale())->toBe('en');

    $this->actingAs($admin)->get(Dashboard::getUrl())->assertOk();

    expect(app()->getLocale())->toBe('en');
});

it('renderiza o painel traduzido (grupos de navegação incluídos) em alemão', function () {
    $admin = User::factory()->create(['locale' => 'de']);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee('Inhalte')        // grupo Content (label via closure)
        ->assertSee('Benutzer')       // nav label Users
        ->assertSee('Einstellungen'); // nav label Settings
});

it('renderiza o painel em inglês (default do site) sem escolha do user', function () {
    $admin = User::factory()->create(['locale' => null]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee('Content')
        ->assertSee('Settings');
});

it('o user pode gravar o idioma no perfil', function () {
    $admin = User::factory()->create(['locale' => null]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Pest\Livewire\livewire(\App\Filament\Pages\EditProfile::class)
        ->fillForm(['locale' => 'en'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh()->locale)->toBe('en');
});
