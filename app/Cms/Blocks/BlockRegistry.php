<?php

namespace App\Cms\Blocks;

use Illuminate\Support\Facades\File;

/**
 * Paleta de blocos em runtime. Lê EXCLUSIVAMENTE o manifesto committed
 * (resources/data/blocks.json, gerado por cms:build) — nunca faz scan de
 * disco por request (G3/G5).
 */
class BlockRegistry
{
    private ?array $manifest = null;

    /** @var array<string, string> diretórios de blocos de plugins ativos: slug => path */
    private array $pluginBlockDirs = [];

    public function registerPluginBlockDir(string $pluginSlug, string $path): void
    {
        $this->pluginBlockDirs[$pluginSlug] = $path;
    }

    /** @return array<string, array> key => definição (manifesto) */
    public function blocks(): array
    {
        return $this->manifest()['blocks'] ?? [];
    }

    public function definition(string $key): ?array
    {
        return $this->blocks()[$key] ?? null;
    }

    /** View Blade a renderizar para uma instância deste bloco. */
    public function viewFor(string $key): ?string
    {
        return $this->definition($key)['view'] ?? null;
    }

    /**
     * Fontes de blocos para o cms:build (scan de disco — só dev/CI):
     * [['key' => ..., 'view' => ..., 'plugin' => ...], ...]
     */
    public function discoverSources(): array
    {
        $sources = [];

        $coreDir = resource_path('views/cms/blocks');
        foreach ($this->bladeFiles($coreDir) as $name) {
            $sources[] = [
                'key' => $name,
                'view' => 'cms.blocks.'.$name,
                'plugin' => null,
            ];
        }

        foreach ($this->pluginBlockDirs as $slug => $dir) {
            foreach ($this->bladeFiles($dir) as $name) {
                $sources[] = [
                    'key' => $slug.'.'.$name,
                    'view' => $slug.'::blocks.'.$name,
                    'plugin' => $slug,
                ];
            }
        }

        return $sources;
    }

    public function manifestPath(): string
    {
        return resource_path(config('cms.blocks.manifest'));
    }

    private function manifest(): array
    {
        if ($this->manifest === null) {
            $path = $this->manifestPath();
            $this->manifest = File::exists($path)
                ? json_decode(File::get($path), true) ?? []
                : [];
        }

        return $this->manifest;
    }

    private function bladeFiles(string $dir): array
    {
        if (! File::isDirectory($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.blade.php'))
            ->map(fn ($f) => substr($f->getFilename(), 0, -strlen('.blade.php')))
            ->values()
            ->all();
    }
}
