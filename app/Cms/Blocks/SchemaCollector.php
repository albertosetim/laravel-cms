<?php

namespace App\Cms\Blocks;

/**
 * Recetor dos registos feitos pelos componentes x-cms em modo collect.
 * Usado exclusivamente pelo cms:build (dev/CI) — nunca em runtime público.
 */
class SchemaCollector
{
    private array $blocks = [];

    private ?string $currentKey = null;

    /** Nome do repeater aberto, se os campos estiverem a registar-se dentro de um. */
    private ?string $openRepeater = null;

    public function startBlock(string $key, string $view, ?string $plugin = null): void
    {
        $this->currentKey = $key;
        $this->blocks[$key] = [
            'key' => $key,
            'view' => $view,
            'plugin' => $plugin,
            'label' => $key,
            'icon' => null,
            'fields' => [],
        ];
    }

    public function setBlockMeta(string $name, ?string $label, ?string $icon): void
    {
        $this->assertOpen();
        $this->blocks[$this->currentKey]['label'] = $label ?? $name;
        $this->blocks[$this->currentKey]['icon'] = $icon;
    }

    public function addField(string $name, string $type, array $extra = []): void
    {
        $this->assertOpen();

        $field = array_merge(['name' => $name, 'type' => $type], array_filter($extra, fn ($v) => $v !== null));

        if ($this->openRepeater !== null) {
            $this->blocks[$this->currentKey]['fields'] = array_map(
                function (array $f) use ($field) {
                    if ($f['type'] === 'repeater' && $f['name'] === $this->openRepeater) {
                        $f['fields'][] = $field;
                    }

                    return $f;
                },
                $this->blocks[$this->currentKey]['fields'],
            );

            return;
        }

        $this->blocks[$this->currentKey]['fields'][] = $field;
    }

    public function openRepeater(string $name, array $extra = []): void
    {
        $this->assertOpen();
        $this->blocks[$this->currentKey]['fields'][] = array_merge(
            ['name' => $name, 'type' => 'repeater', 'fields' => []],
            array_filter($extra, fn ($v) => $v !== null),
        );
        $this->openRepeater = $name;
    }

    public function closeRepeater(): void
    {
        $this->openRepeater = null;
    }

    public function blocks(): array
    {
        ksort($this->blocks);

        return $this->blocks;
    }

    public function resetBlocks(): void
    {
        $this->blocks = [];
        $this->currentKey = null;
        $this->openRepeater = null;
    }

    private function assertOpen(): void
    {
        if ($this->currentKey === null) {
            throw new \LogicException('x-cms registado fora de um bloco em modo collect.');
        }
    }
}
