<?php

namespace App\Cms\Plugins;

use App\Cms\Blocks\BlockRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Base de um plugin: ServiceProvider + metadata (08-plugins). Convenção
 * sobre configuração — largar ficheiros nas subpastas certas chega:
 *
 *   app/Plugins/{Nome}/
 *     Plugin.php                (extends PluginProvider)
 *     routes.php
 *     config.php
 *     Models/
 *     database/migrations/
 *     resources/views/blocks/   (blocos: entram na paleta via cms:build)
 *
 * Plugins são código first-party deployado por git — nunca upload em runtime.
 */
abstract class PluginProvider extends ServiceProvider
{
    abstract public function slug(): string;

    abstract public function name(): string;

    public function version(): string
    {
        return '1.0.0';
    }

    /** Slugs de plugins de que este depende (ordem de boot garantida pelo sync). */
    public function dependsOn(): array
    {
        return [];
    }

    public function boot(): void
    {
        $dir = $this->basePath();

        if (is_dir($dir.'/database/migrations')) {
            $this->loadMigrationsFrom($dir.'/database/migrations');
        }

        if (is_dir($dir.'/resources/views')) {
            $this->loadViewsFrom($dir.'/resources/views', $this->slug());
        }

        if (is_dir($dir.'/resources/views/blocks')) {
            app(BlockRegistry::class)->registerPluginBlockDir($this->slug(), $dir.'/resources/views/blocks');
        }

        if (file_exists($dir.'/routes.php')) {
            $this->loadRoutesFrom($dir.'/routes.php');
        }

        if (file_exists($dir.'/config.php')) {
            $this->mergeConfigFrom($dir.'/config.php', 'cms.plugins.'.$this->slug());
        }
    }

    protected function basePath(): string
    {
        return dirname((new \ReflectionClass($this))->getFileName());
    }
}
