<?php

namespace App\Console\Commands;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\SchemaCollector;
use App\Cms\Render\CmsRenderContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Extrai os blueprints de todos os blocos (core + plugins ativos) para o
 * manifesto committed. Corre em dev e CI — NUNCA por request (G1/G5).
 */
class CmsBuild extends Command
{
    protected $signature = 'cms:build {--check : Falha se o manifesto committed estiver desatualizado (CI)}';

    protected $description = 'Extrai blueprints dos blocos x-cms para resources/data/blocks.json';

    public function handle(BlockRegistry $registry, CmsRenderContext $context, SchemaCollector $collector): int
    {
        $sources = $registry->discoverSources();

        if ($sources === []) {
            $this->warn('Nenhum bloco encontrado.');
        }

        // Dupla renderização: deteta campos declarados condicionalmente
        // (registo instável) — regra de autoria do 04-blocks.
        $first = $this->collect($sources, $context, $collector);
        $second = $this->collect($sources, $context, $collector);

        if ($first !== $second) {
            $this->error('Registo instável de campos: há x-cms declarados dentro de @if/@foreach.');

            return self::FAILURE;
        }

        foreach ($first as $key => $block) {
            if ($block['fields'] === []) {
                $this->warn("Bloco '{$key}' não declara nenhum campo.");
            }
        }

        $manifest = json_encode(['blocks' => $first], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $path = $registry->manifestPath();

        if ($this->option('check')) {
            $current = File::exists($path) ? File::get($path) : '';

            if (trim($current) !== trim($manifest)) {
                $this->error('Manifesto desatualizado: corre `php artisan cms:build` e commita o resultado.');

                return self::FAILURE;
            }

            $this->info('Manifesto em sincronia com os blocos.');

            return self::SUCCESS;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $manifest."\n");

        $this->info(count($first).' bloco(s) extraído(s) para '.str_replace(base_path().'/', '', $path));

        return self::SUCCESS;
    }

    private function collect(array $sources, CmsRenderContext $context, SchemaCollector $collector): array
    {
        $collector->resetBlocks();
        $context->setMode(CmsRenderContext::MODE_COLLECT);

        try {
            foreach ($sources as $source) {
                $collector->startBlock($source['key'], $source['view'], $source['plugin']);
                view($source['view'])->render();
            }
        } finally {
            $context->setMode(CmsRenderContext::MODE_VIEW);
        }

        return $collector->blocks();
    }
}
