<?php

namespace App\Providers;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\SchemaCollector;
use App\Cms\Render\CmsRenderContext;
use App\Models\Cms\ContentType;
use App\Models\Cms\Page;
use App\Models\User;
use App\Policies\Cms\ContentTypePolicy;
use App\Policies\Cms\PagePolicy;
use App\Support\Settings;
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
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(ContentType::class, ContentTypePolicy::class);

        // admin e developer são super-roles: passam todas as policies/gates.
        Gate::before(fn (User $user) => $user->hasAnyRole(['admin', 'developer']) ? true : null);

        // Acesso aos System Logs (storage/logs) restrito a administradores.
        Gate::define('viewSystemLogs', fn (User $user) => $user->hasRole('admin'));

        // Gestão das settings (geral + por tipo) restrita a administradores.
        Gate::define('manageSettings', fn (User $user) => $user->hasRole('admin'));

        $this->applyTimezone();

        Blade::component('cms.blocks', Blocks::class);
        Blade::component('cms.block', Block::class);
        Blade::component('cms.field', Field::class);
        Blade::component('cms.repeater', Repeater::class);
    }

    /**
     * Timezone vinda das general settings (DB ?? .env). Defensivo: em console
     * (migrate, antes da tabela existir) ou se as settings falharem, não rebenta.
     */
    private function applyTimezone(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        try {
            if ($tz = Settings::general()->get('timezone')) {
                date_default_timezone_set($tz);
                config(['app.timezone' => $tz]);
            }
        } catch (\Throwable) {
            // settings ainda não disponíveis — ignora.
        }
    }
}
