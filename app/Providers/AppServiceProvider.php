<?php

namespace App\Providers;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\SchemaCollector;
use App\Cms\Render\CmsRenderContext;
use App\View\Components\Cms\Block;
use App\View\Components\Cms\Blocks;
use App\View\Components\Cms\Field;
use App\View\Components\Cms\Repeater;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
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

        Blade::component('cms.blocks', Blocks::class);
        Blade::component('cms.block', Block::class);
        Blade::component('cms.field', Field::class);
        Blade::component('cms.repeater', Repeater::class);
    }
}
