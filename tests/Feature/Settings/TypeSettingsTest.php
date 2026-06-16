<?php

use App\Filament\Resources\Blog\Pages\ListBlog;
use App\Models\Blog;
use App\Models\Cms\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('faz round-trip das settings ao nível do tipo', function () {
    Blog::settings()->set('per_page', 12);

    expect(Blog::settings()->get('per_page'))->toBe(12)
        ->and(Blog::settings()->get('inexistente', 'fallback'))->toBe('fallback');
});

it('isola as settings entre tipos diferentes', function () {
    Blog::settings()->set('per_page', 12);

    expect(Page::settings()->get('per_page'))->toBeNull();
});

it('replace apaga as chaves ausentes', function () {
    Blog::settings()->set('a', 1);
    Blog::settings()->set('b', 2);

    Blog::settings()->replace(['a' => 9]);

    expect(Blog::settings()->get('a'))->toBe(9)
        ->and(Blog::settings()->get('b'))->toBeNull();
});

it('o botão Settings na listagem grava as settings do tipo', function () {
    Role::findOrCreate('admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Pest\Livewire\livewire(ListBlog::class)
        ->assertActionExists('settings')
        ->callAction('settings', data: ['settings' => ['featured_count' => '3']]);

    expect(Blog::settings()->get('featured_count'))->toBe('3');
});
