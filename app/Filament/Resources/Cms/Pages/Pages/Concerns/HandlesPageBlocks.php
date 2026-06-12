<?php

namespace App\Filament\Resources\Cms\Pages\Pages\Concerns;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Render\RichTextSanitizer;
use Illuminate\Support\Str;

/**
 * Mapeia entre o documento de blocos da revision ({id, block, values}) e o
 * estado do Builder do Filament ({type, data}), com sanitização de richtext
 * na gravação.
 */
trait HandlesPageBlocks
{
    /** Revision → estado do form. */
    protected function blocksToBuilderState(array $instances): array
    {
        return array_map(fn (array $instance) => [
            'type' => $instance['block'] ?? '',
            'data' => $instance['values'] ?? [],
        ], $instances);
    }

    /** Estado do form → documento da revision (sanitizado). */
    protected function builderStateToBlocks(array $state): array
    {
        $registry = app(BlockRegistry::class);
        $sanitizer = app(RichTextSanitizer::class);

        return collect($state)
            ->map(function (array $item) use ($registry, $sanitizer) {
                $key = $item['type'] ?? '';
                $fields = $registry->definition($key)['fields'] ?? [];

                return [
                    'id' => (string) Str::uuid(),
                    'block' => $key,
                    'values' => $sanitizer->sanitizeValues($item['data'] ?? [], $fields),
                ];
            })
            ->values()
            ->all();
    }
}
