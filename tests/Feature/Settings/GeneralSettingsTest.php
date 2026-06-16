<?php

use App\Filament\Pages\Settings as SettingsPage;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('só admins acedem à página de settings', function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('editor');

    $url = SettingsPage::getUrl();

    $this->get($url)->assertRedirect(route('filament.admin.auth.login'));

    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $this->actingAs($editor)->get($url)->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin)->get($url)->assertOk();
});

it('cai para os defaults do .env quando a DB está vazia', function () {
    expect(Settings::general()->get('site_name'))->toBe(config('settings.defaults.site_name'))
        ->and(Settings::general()->get('contact_email'))->toBe('info@web-crossing.com');
});

it('persiste e lê de volta o que foi gravado', function () {
    Settings::general()->set('site_name', 'Cliente XPTO');

    expect(Settings::general()->get('site_name'))->toBe('Cliente XPTO')
        ->and(\App\Models\Cms\Setting::where('settable_type', 'general')->where('key', 'site_name')->exists())->toBeTrue();
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
