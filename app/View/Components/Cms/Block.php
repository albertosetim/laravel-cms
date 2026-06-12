<?php

namespace App\View\Components\Cms;

use App\Cms\Blocks\SchemaCollector;
use App\Cms\Render\CmsRenderContext;
use Illuminate\View\Component;

/**
 * <x-cms.block name="hero" label="Hero" icon="photo"> ... </x-cms.block>
 * Wrapper de um bloco. Em collect regista a metadata e renderiza o slot para
 * os campos lá dentro também se registarem; em view só renderiza o slot.
 */
class Block extends Component
{
    public function __construct(
        public string $name,
        public ?string $label = null,
        public ?string $icon = null,
    ) {
    }

    public function render(): \Closure
    {
        return function (array $data) {
            if (app(CmsRenderContext::class)->isCollecting()) {
                app(SchemaCollector::class)->setBlockMeta($this->name, $this->label, $this->icon);
            }

            return (string) $data['slot'];
        };
    }
}
