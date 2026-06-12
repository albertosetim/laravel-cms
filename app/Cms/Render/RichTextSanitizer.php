<?php

namespace App\Cms\Render;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitização de richtext NA GRAVAÇÃO (07-backend): o render confia no HTML
 * porque a entrada foi limpa — defesa na escrita, não na leitura.
 */
class RichTextSanitizer
{
    private ?HTMLPurifier $purifier = null;

    public function sanitize(string $html): string
    {
        return $this->purifier()->purify($html);
    }

    /**
     * Limpa os campos richtext de um conjunto de valores, guiado pelos
     * campos do blueprint (inclui repeaters, um nível).
     */
    public function sanitizeValues(array $values, array $fields): array
    {
        foreach ($fields as $field) {
            $name = $field['name'];

            if (! isset($values[$name])) {
                continue;
            }

            if ($field['type'] === 'richtext' && is_string($values[$name])) {
                $values[$name] = $this->sanitize($values[$name]);
            }

            if ($field['type'] === 'repeater' && is_array($values[$name])) {
                $values[$name] = array_map(
                    fn ($item) => is_array($item)
                        ? $this->sanitizeValues($item, $field['fields'] ?? [])
                        : $item,
                    $values[$name],
                );
            }
        }

        return $values;
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', 'p,br,strong,em,u,s,a[href|title|rel],ul,ol,li,h2,h3,h4,blockquote,code,pre');
            $config->set('Cache.SerializerPath', storage_path('framework/cache'));

            $this->purifier = new HTMLPurifier($config);
        }

        return $this->purifier;
    }
}
