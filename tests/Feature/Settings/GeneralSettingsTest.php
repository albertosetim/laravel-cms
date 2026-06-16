<?php

use App\Filament\Livewire\SettingsModal;
use App\Models\Cms\Setting;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('só admins acedem ao modal de settings e gravam', function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('editor');

    // Editor: o mount() aborta 403.
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    Livewire::actingAs($editor)->test(SettingsModal::class)->assertForbidden();

    // Admin: monta, edita e grava → persiste nas general settings.
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)->test(SettingsModal::class)
        ->set('data.site_name', 'Cliente XPTO')
        ->set('data.contact_email', 'info@web-crossing.com')
        ->set('data.timezone', 'Europe/Vienna')
        ->call('save')
        ->assertHasNoErrors();

    expect(Settings::general()->get('site_name'))->toBe('Cliente XPTO');
});

it('cai para os defaults do .env quando a DB está vazia', function () {
    expect(Settings::general()->get('site_name'))->toBe(config('settings.defaults.site_name'))
        ->and(Settings::general()->get('contact_email'))->toBe('info@example.com');
});

it('persiste e lê de volta o que foi gravado', function () {
    Settings::general()->set('site_name', 'Cliente XPTO');

    expect(Settings::general()->get('site_name'))->toBe('Cliente XPTO')
        ->and(Setting::where('settable_type', 'general')->where('key', 'site_name')->exists())->toBeTrue();
});

it('liga o modo manutenção só para visitantes anónimos', function () {
    Role::findOrCreate('admin');

    Settings::general()->set('maintenance_mode', true);

    // Anónimo → 503 (a raiz nem chega ao redirect).
    $this->get('/')->assertStatus(503);

    // Autenticado passa pelo middleware (redirect da raiz).
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin)->get('/')->assertRedirect('/'.config('cms.default_locale'));
});

it('deixa passar quando o modo manutenção está desligado', function () {
    Settings::general()->set('maintenance_mode', false);

    $this->get('/')->assertRedirect('/'.config('cms.default_locale'));
});
