<?php

namespace App\View\Components\Cms;

use App\Cms\Blocks\SchemaCollector;
use App\Cms\Render\CmsRenderContext;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Component;

/**
 * <x-cms.field name="title" type="text" />
 * Em collect regista-se no SchemaCollector e não produz output; em view lê o
 * valor do scope corrente e renderiza-o conforme o type.
 *
 * Regra de autoria (04-blocks): campos declaram-se incondicionalmente —
 * nunca dentro de @if/@foreach. O cms:build valida por dupla renderização.
 */
class Field extends Component
{
    public function __construct(
        public string $name,
        public string $type = 'text',
        public ?string $label = null,
        public bool $required = false,
        public mixed $default = null,
        /** Para type=select: lista de opções. */
        public array $options = [],
    ) {
    }

    public function render(): string
    {
        $context = app(CmsRenderContext::class);

        if ($context->isCollecting()) {
            app(SchemaCollector::class)->addField($this->name, $this->type, [
                'label' => $this->label,
                'required' => $this->required ?: null,
                'default' => $this->default,
                'options' => $this->options !== [] ? $this->options : null,
            ]);

            return '';
        }

        $value = $context->value($this->name, $this->default);

        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        return match ($this->type) {
            // Sanitizado na gravação (defesa na escrita, ver SaveDraft/Purifier).
            'richtext' => (string) $value,
            'media' => $this->renderMedia($value),
            'link' => $this->renderLink($value),
            'menu' => $this->renderMenu($value),
            'boolean' => $value ? '1' : '',
            default => e(is_scalar($value) ? (string) $value : json_encode($value)),
        };
    }

    private function renderMenu(mixed $value): string
    {
        $menuId = is_array($value) ? ($value['id'] ?? null) : $value;

        if (! is_numeric($menuId)) {
            return '';
        }

        $menu = \App\Models\Cms\Menu::find($menuId);

        if ($menu === null) {
            return '';
        }

        return view('cms.menu', ['items' => $menu->items ?? []])->render();
    }

    private function renderMedia(mixed $value): string
    {
        $path = is_array($value) ? ($value['path'] ?? null) : $value;

        if (! is_string($path) || $path === '') {
            return '';
        }

        $alt = is_array($value) ? ($value['alt'] ?? '') : '';

        return '<img src="'.e(Storage::disk('public')->url($path)).'" alt="'.e($alt).'" loading="lazy">';
    }

    private function renderLink(mixed $value): string
    {
        if (! is_array($value)) {
            return '';
        }

        // Link interno por id — sobrevive a renames de slug.
        $url = \App\Support\Cms\CmsUrl::forItem($value);

        if ($url === null) {
            return '';
        }

        $label = $value['label'] ?? $url;

        if (! empty($value['page_id']) && empty($value['label'])) {
            $label = \App\Models\Cms\Page::find($value['page_id'])?->name ?? $url;
        }

        return '<a href="'.e($url).'">'.e((string) $label).'</a>';
    }
}
