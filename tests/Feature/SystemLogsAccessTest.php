<?php

use App\Filament\Pages\SystemLogs;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('allows admins and blocks others from the system logs', function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('editor');

    $url = SystemLogs::getUrl();

    // Convidado primeiro: actingAs() fixa o utilizador para o resto do teste.
    $this->get($url)->assertRedirect(route('filament.admin.auth.login'));

    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $this->actingAs($editor)->get($url)->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin)->get($url)->assertOk();
});
