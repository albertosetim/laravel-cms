<?php

namespace App\Cms\Generator;

use Illuminate\Support\Str;

/**
 * Diff puro entre o schema gerado (snapshot) e o blueprint atual de um
 * ContentType. Não toca em disco nem na BD — devolve operações estruturadas que
 * o TypeGenerator transforma numa migration de ALTER. Mantido separado para ser
 * trivialmente testável.
 */
class SchemaDiffer
{
    /**
     * Spec da coluna de um campo. Devolve null para campos sem coluna própria
     * (media é gerido pela tabela morph do Spatie, não tem coluna na tabela).
     *
     * @return array{name: string, method: string, nullable: bool, indexed: bool}|null
     */
    public static function columnSpec(array $field): ?array
    {
        $type = $field['type'] ?? 'text';

        if ($type === 'media') {
            return null;
        }

        $method = match ($type) {
            'textarea' => 'text',
            'richtext' => 'longText',
            'number' => 'integer',
            'boolean' => 'boolean',
            'date' => 'date',
            'select', 'link' => 'string',
            'menu' => 'unsignedBigInteger',
            'repeater' => 'jsonb',
            default => 'string',
        };

        $indexed = (! empty($field['listable']) || $type === 'select')
            && ! in_array($method, ['text', 'longText', 'jsonb'], true);

        return [
            'name' => Str::snake($field['name']),
            'method' => $method,
            'nullable' => empty($field['required']),
            'indexed' => $indexed,
        ];
    }

    /**
     * Diff das colunas de campos (sem relações).
     *
     * @return array{added: list<array>, dropped: list<array>, changed: list<array{from: array, to: array}>}
     */
    public function diffFields(array $oldFields, array $newFields): array
    {
        $old = $this->specsByName($oldFields);
        $new = $this->specsByName($newFields);

        $added = [];
        $changed = [];
        foreach ($new as $name => $spec) {
            if (! isset($old[$name])) {
                $added[] = $spec;
            } elseif ($old[$name] !== $spec) {
                $changed[] = ['from' => $old[$name], 'to' => $spec];
            }
        }

        $dropped = [];
        foreach ($old as $name => $spec) {
            if (! isset($new[$name])) {
                $dropped[] = $spec;
            }
        }

        return compact('added', 'dropped', 'changed');
    }

    /**
     * Diff das relações. Compara por (tipo:nome:alvo) — mudar o alvo/tipo conta
     * como remover a antiga + adicionar a nova, o que produz o ALTER correto.
     *
     * @return array{added: list<array>, dropped: list<array>}
     */
    public function diffRelations(array $oldRelations, array $newRelations): array
    {
        $sig = fn (array $r): string => ($r['type'] ?? '').':'.($r['name'] ?? '').':'.ltrim($r['target'] ?? '', '\\');

        $old = [];
        foreach ($oldRelations as $r) {
            $old[$sig($r)] = $r;
        }
        $new = [];
        foreach ($newRelations as $r) {
            $new[$sig($r)] = $r;
        }

        $added = [];
        foreach ($new as $key => $r) {
            if (! isset($old[$key])) {
                $added[] = $r;
            }
        }

        $dropped = [];
        foreach ($old as $key => $r) {
            if (! isset($new[$key])) {
                $dropped[] = $r;
            }
        }

        return compact('added', 'dropped');
    }

    /**
     * @param  array{added: list<array>, dropped: list<array>, changed: list<array>}  $fieldDiff
     * @param  array{added: list<array>, dropped: list<array>}  $relationDiff
     */
    public function isEmpty(array $fieldDiff, array $relationDiff): bool
    {
        return $fieldDiff['added'] === []
            && $fieldDiff['dropped'] === []
            && $fieldDiff['changed'] === []
            && $relationDiff['added'] === []
            && $relationDiff['dropped'] === [];
    }

    /** @return array<string, array> specs indexadas por nome de coluna */
    private function specsByName(array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            $spec = self::columnSpec($field);
            if ($spec !== null) {
                $out[$spec['name']] = $spec;
            }
        }

        return $out;
    }
}
