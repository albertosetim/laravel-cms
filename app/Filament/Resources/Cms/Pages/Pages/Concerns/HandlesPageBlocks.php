<?php

namespace App\Filament\Resources\Cms\Pages\Pages\Concerns;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Render\RichTextSanitizer;
use Illuminate\Support\Str;

/**
 * Mapeia entre o documento de blocos da revision ({id, block, column, values})
 * e o estado do Builder do Filament ({type, data}), com sanitização de
 * richtext na gravação. A coluna do layout viaja como __column (1-based) dentro
 * do data do builder e converte-se para o índice column (0-based) da instância.
 */
trait HandlesPageBlocks
{
    /** Revision → estado do form. */
    protected function blocksToBuilderState(array $instances): array
    {
        return array_map(function (array $instance) {
            $data = $instance['values'] ?? [];
            $data['__column'] = ((int) ($instance['column'] ?? 0)) + 1;

            return [
                'type' => $instance['block'] ?? '',
                'data' => $data,
            ];
        }, $instances);
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
                $data = $item['data'] ?? [];

                $column = max(((int) ($data['__column'] ?? 1)) - 1, 0);
                unset($data['__column']);

                return [
                    'id' => (string) Str::uuid(),
                    'block' => $key,
                    'column' => $column,
                    'values' => $sanitizer->sanitizeValues($data, $fields),
                ];
            })
            ->values()
            ->all();
    }
}
