<?php

namespace App\View\Components\Cms;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Render\CmsRenderContext;
use App\Models\Cms\PageRevision;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;

/**
 * <x-cms.blocks :revision="$revision" /> — renderiza o documento de blocos
 * de uma revision, distribuído pela grelha do layout da página (config
 * cms.layouts). Bloco desconhecido (ex.: plugin desativado) é saltado com
 * warning — nunca rebenta a página pública.
 */
class Blocks extends Component
{
    public function __construct(public PageRevision $revision)
    {
    }

    public function render(): string
    {
        $context = app(CmsRenderContext::class);
        $registry = app(BlockRegistry::class);

        $page = $context->page() ?? $this->revision->page;
        $layoutKey = $page?->layout ?? 'full';
        $widths = config("cms.layouts.{$layoutKey}.columns", [12]);
        $columnCount = count($widths);

        $columns = array_fill(0, $columnCount, '');

        foreach ($this->revision->blockInstances() as $instance) {
            $view = $registry->viewFor($instance['block'] ?? '');

            if ($view === null || ! view()->exists($view)) {
                Log::warning('CMS: bloco desconhecido ou indisponível saltado no render.', [
                    'block' => $instance['block'] ?? null,
                    'page_id' => $this->revision->page_id,
                ]);

                continue;
            }

            $context->pushScope($instance['values'] ?? []);

            try {
                $html = view($view)->render();
            } finally {
                $context->popScope();
            }

            // Coluna clampada ao número real de colunas do layout.
            $column = min(max((int) ($instance['column'] ?? 0), 0), $columnCount - 1);
            $columns[$column] .= $html;
        }

        if ($columnCount === 1) {
            return $columns[0];
        }

        $grid = '<div class="cms-grid">';

        foreach ($widths as $i => $width) {
            $grid .= '<div class="cms-col-'.$width.'">'.$columns[$i].'</div>';
        }

        return $grid.'</div>';
    }
}
