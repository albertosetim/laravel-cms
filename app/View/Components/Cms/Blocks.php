<?php

namespace App\View\Components\Cms;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Render\CmsRenderContext;
use App\Models\Cms\PageRevision;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;

/**
 * <x-cms.blocks :revision="$revision" /> — renderiza o documento de blocos
 * de uma revision, na ordem do editor. Bloco desconhecido (ex.: plugin
 * desativado) é saltado com warning — nunca rebenta a página pública.
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

        $html = '';

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
                $html .= view($view)->render();
            } finally {
                $context->popScope();
            }
        }

        return $html;
    }
}
