<?php

namespace Database\Seeders;

use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\User;
use App\Services\Cms\PagePublisher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'publisher', 'editor', 'developer'] as $role) {
            Role::findOrCreate($role);
        }

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@laravel-cms.test'],
            ['name' => 'Admin', 'password' => 'password'],
        );
        $admin->syncRoles(['admin']);

        // Homepage de exemplo por locale, já publicada.
        foreach (config('cms.locales') as $locale) {
            $home = Page::query()->firstOrCreate(
                ['slug' => config('cms.home_slug'), 'locale' => $locale, 'parent_id' => null],
                ['name' => 'Home '.strtoupper($locale), 'template' => 'default'],
            );

            if (! $home->isPublished()) {
                app(PagePublisher::class)->saveDraft($home, ['blocks' => [
                    [
                        'id' => (string) Str::uuid(),
                        'block' => 'hero',
                        'values' => [
                            'title' => $locale === 'de' ? 'Willkommen' : 'Welcome',
                            'subtitle' => 'laravel-cms — blocos, tipos e plugins sem sair do Laravel.',
                            'cta' => ['url' => 'https://github.com/albertosetim/laravel-cms', 'label' => 'GitHub'],
                        ],
                    ],
                ]], $admin->id);

                app(PagePublisher::class)->publish($home, $admin->id);
            }
        }

        // Menu principal de exemplo, ligado às homepages por id (sobrevive a renames).
        $homeDe = Page::query()
            ->where('locale', config('cms.default_locale'))
            ->where('slug', config('cms.home_slug'))
            ->first();

        Menu::query()->firstOrCreate(
            ['slug' => 'main'],
            [
                'name' => 'Menu Principal',
                'items' => $homeDe ? [
                    ['label' => 'Início', 'type' => 'page', 'page_id' => $homeDe->id, 'children' => []],
                ] : [],
            ],
        );
    }
}
