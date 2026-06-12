<?php

namespace App\Cms\Plugins;

use App\Models\Cms\Plugin;
use Illuminate\Support\Facades\File;

/**
 * Descoberta e materialização do cache de plugins. O scan de disco e a
 * escrita do cache acontecem SÓ via artisan (dev/deploy) — o boot lê o
 * ficheiro e mais nada (G1/G3).
 */
class PluginManager
{
    public function cachePath(): string
    {
        return app()->bootstrapPath(config('cms.plugins.cache_file'));
    }

    /** @return array<class-string<PluginProvider>> lidos do cache (boot). */
    public function cachedProviders(): array
    {
        $path = $this->cachePath();

        return File::exists($path) ? require $path : [];
    }

    /**
     * Scan de app/Plugins/{Nome}/Plugin.php (só dev/deploy).
     *
     * @return array<string, PluginProvider> slug => instância
     */
    public function discover(): array
    {
        $found = [];

        foreach (File::glob(app_path('Plugins/*/Plugin.php')) as $file) {
            $class = 'App\\Plugins\\'.basename(dirname($file)).'\\Plugin';

            if (! class_exists($class) || ! is_subclass_of($class, PluginProvider::class)) {
                continue;
            }

            /** @var PluginProvider $instance */
            $instance = new $class(app());
            $found[$instance->slug()] = $instance;
        }

        return $found;
    }

    /**
     * Upsert no catálogo (novos entram desativados) e reescreve o cache com
     * os ativos, em ordem de dependências. Falha em ciclos e dependências
     * de plugins inativos.
     */
    public function sync(): array
    {
        $found = $this->discover();

        foreach ($found as $slug => $plugin) {
            Plugin::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $plugin->name(), 'version' => $plugin->version()],
            );
        }

        $enabled = Plugin::query()
            ->where('enabled', true)
            ->pluck('slug')
            ->filter(fn (string $slug) => isset($found[$slug]))
            ->values()
            ->all();

        $ordered = $this->resolveOrder($enabled, $found);

        $this->writeCache(array_map(fn (string $slug) => $found[$slug]::class, $ordered));

        return $ordered;
    }

    public function setEnabled(string $slug, bool $enabled): void
    {
        $plugin = Plugin::query()->where('slug', $slug)->firstOrFail();

        if (! $enabled) {
            $dependents = $this->enabledDependentsOf($slug);

            if ($dependents !== []) {
                throw new \RuntimeException(
                    "Não é possível desativar '{$slug}': ".implode(', ', $dependents).' depende(m) dele.',
                );
            }
        }

        $plugin->update(['enabled' => $enabled]);
        $this->sync();
    }

    /** @param array<string> $enabled @param array<string, PluginProvider> $found */
    private function resolveOrder(array $enabled, array $found): array
    {
        $ordered = [];
        $visiting = [];

        $visit = function (string $slug) use (&$visit, &$ordered, &$visiting, $enabled, $found) {
            if (in_array($slug, $ordered, true)) {
                return;
            }

            if (isset($visiting[$slug])) {
                throw new \RuntimeException("Ciclo de dependências de plugins em '{$slug}'.");
            }

            $visiting[$slug] = true;

            foreach ($found[$slug]->dependsOn() as $dep) {
                if (! in_array($dep, $enabled, true)) {
                    throw new \RuntimeException("Plugin '{$slug}' depende de '{$dep}', que não está ativo.");
                }

                $visit($dep);
            }

            unset($visiting[$slug]);
            $ordered[] = $slug;
        };

        foreach ($enabled as $slug) {
            $visit($slug);
        }

        return $ordered;
    }

    private function writeCache(array $providerClasses): void
    {
        $export = var_export($providerClasses, true);

        File::put(
            $this->cachePath(),
            "<?php\n\n// Gerado por cms:plugins:sync — não editar. O boot lê só isto (G3).\nreturn {$export};\n",
        );
    }

    private function enabledDependentsOf(string $slug): array
    {
        return collect($this->discover())
            ->filter(fn (PluginProvider $p) => in_array($slug, $p->dependsOn(), true))
            ->keys()
            ->filter(fn (string $s) => Plugin::query()->where('slug', $s)->where('enabled', true)->exists())
            ->values()
            ->all();
    }
}
