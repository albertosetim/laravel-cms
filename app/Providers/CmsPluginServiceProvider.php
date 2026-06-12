<?php

namespace App\Providers;

use App\Cms\Plugins\PluginManager;
use Illuminate\Support\ServiceProvider;

/**
 * Regista os plugins ativos lendo SÓ o ficheiro de cache materializado por
 * cms:plugins:sync. Zero queries à DB no boot (G3): a app arranca com a DB
 * em baixo; sem cache, arranca sem plugins.
 */
class CmsPluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginManager::class);

        foreach ($this->app->make(PluginManager::class)->cachedProviders() as $provider) {
            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }
}
