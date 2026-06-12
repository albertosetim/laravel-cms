<?php

namespace App\View\Components\Cms;

use App\Cms\Blocks\SchemaCollector;
use App\Cms\Render\CmsRenderContext;
use Illuminate\View\Component;

/**
 * <x-cms.repeater name="slides" :fields="[['name' => 'image', 'type' => 'media'], ...]"
 *                 item-view="cms.blocks.partials.slide" />
 *
 * Os subcampos declaram-se via prop :fields (não via slot): slots Blade são
 * renderizados uma única vez, por isso não podem re-bindar valores por item.
 * Em view, cada item renderiza o item-view com o scope do item; sem
 * item-view, os subcampos renderizam-se em sequência (markup genérico).
 * Nesting de repeater dentro de repeater: não suportado (04-blocks).
 */
class Repeater extends Component
{
    public function __construct(
        public string $name,
        public array $fields = [],
        public ?string $itemView = null,
        public ?string $label = null,
    ) {
    }

    public function render(): string
    {
        $context = app(CmsRenderContext::class);

        if ($context->isCollecting()) {
            $collector = app(SchemaCollector::class);
            $collector->openRepeater($this->name, ['label' => $this->label]);

            foreach ($this->fields as $field) {
                $collector->addField(
                    $field['name'],
                    $field['type'] ?? 'text',
                    collect($field)->except(['name', 'type'])->all(),
                );
            }

            $collector->closeRepeater();

            return '';
        }

        $items = $context->value($this->name, []);

        if (! is_array($items) || $items === []) {
            return '';
        }

        $html = '';

        foreach ($items as $item) {
            $values = is_array($item) ? $item : [];
            $context->pushScope($values);

            try {
                $html .= $this->itemView !== null
                    ? view($this->itemView, ['item' => $values])->render()
                    : $this->renderGenericItem($values);
            } finally {
                $context->popScope();
            }
        }

        return $html;
    }

    private function renderGenericItem(array $values): string
    {
        $html = '<div class="cms-repeater-item">';

        foreach ($this->fields as $field) {
            $component = new Field(
                name: $field['name'],
                type: $field['type'] ?? 'text',
                default: $field['default'] ?? null,
            );
            $html .= $component->render();
        }

        return $html.'</div>';
    }
}
