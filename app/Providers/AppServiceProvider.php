<?php

namespace App\Providers;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\SchemaCollector;
use App\Cms\Render\CmsRenderContext;
use App\Filament\Resources\Cms\Entries\EntryResource;
use App\Models\Cms\ContentType;
use App\View\Components\Cms\Block;
use App\View\Components\Cms\Blocks;
use App\View\Components\Cms\Field;
use App\View\Components\Cms\Repeater;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Estado por-request: scoped, nunca singleton (Octane/queues).
        $this->app->scoped(CmsRenderContext::class);
        $this->app->scoped(SchemaCollector::class);

        $this->app->singleton(BlockRegistry::class);
    }

    public function boot(): void
    {
        Gate::policy(\App\Models\Cms\Page::class, \App\Policies\Cms\PagePolicy::class);
        Gate::policy(\App\Models\Cms\ContentType::class, \App\Policies\Cms\ContentTypePolicy::class);
        Gate::policy(\App\Models\Cms\Entry::class, \App\Policies\Cms\EntryPolicy::class);

        Blade::component('cms.blocks', Blocks::class);
        Blade::component('cms.block', Block::class);
        Blade::component('cms.field', Field::class);
        Blade::component('cms.repeater', Repeater::class);

        $this->bootCmsNavigation();
    }

    /**
     * Cada tipo de conteúdo ganha o seu próprio item no menu lateral (grupo
     * "Conteúdo"), que abre o CRUD genérico filtrado por esse tipo. Registado
     * em serving() — corre por-request ao servir o painel, nunca no boot da
     * app (G3 intacto: o boot não toca na DB).
     */
    private function bootCmsNavigation(): void
    {
        Filament::serving(function () {
            if (! Schema::hasTable('cms_types')) {
                return;
            }

            $items = ContentType::query()
                ->where('promoted', false)
                ->orderBy('name')
                ->get()
                ->map(fn (ContentType $type) => NavigationItem::make($type->name)
                    ->group('Conteúdo')
                    ->icon($type->icon ?: 'heroicon-o-rectangle-stack')
                    ->url(EntryResource::getUrl('index', ['type' => $type->slug]))
                    ->isActiveWhen(fn () => request()->routeIs(EntryResource::getRouteBaseName().'.*')
                        && request('type') === $type->slug))
                ->all();

            Filament::registerNavigationItems($items);
        });
    }
}
