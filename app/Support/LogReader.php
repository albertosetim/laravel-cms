<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Leitor nativo dos ficheiros de log do Laravel (storage/logs).
 * Substitui o opcodesio/log-viewer por uma fonte de dados para tabelas Filament.
 */
class LogReader
{
    /** Cabeçalho de uma entrada: [2026-06-16 09:58:12] local.ERROR: mensagem */
    private const ENTRY_PATTERN = '/^\[(?<date>\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2})?)\]\s+(?<env>[\w\-]+)\.(?<level>\w+):\s?(?<message>.*)$/';

    public function __construct(protected string $directory)
    {
    }

    public static function make(): static
    {
        return new static(storage_path('logs'));
    }

    /**
     * Ficheiros .log disponíveis, mais recente primeiro.
     *
     * @return Collection<int, array{name: string, size: int, modified: int}>
     */
    public function files(): Collection
    {
        return collect(glob($this->directory.'/*.log') ?: [])
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'size' => filesize($path) ?: 0,
                'modified' => filemtime($path) ?: 0,
            ])
            ->sortByDesc('modified')
            ->values();
    }

    public function defaultFile(): ?string
    {
        return $this->files()->first()['name'] ?? null;
    }

    /**
     * Entradas de um ficheiro, mais recente primeiro.
     *
     * @return Collection<int, array{__key: string, datetime: string, environment: string, level: string, message: string, context: string, full: string}>
     */
    public function entries(?string $file): Collection
    {
        if (blank($file)) {
            return collect();
        }

        $path = $this->directory.'/'.basename($file);

        if (! is_file($path) || ! is_readable($path)) {
            return collect();
        }

        $entries = [];
        $current = null;
        $index = 0;

        foreach (preg_split('/\R/', (string) file_get_contents($path)) as $line) {
            if (preg_match(self::ENTRY_PATTERN, $line, $m)) {
                if ($current !== null) {
                    $entries[] = $this->finalize($current);
                }

                $current = [
                    'index' => $index++,
                    'datetime' => $m['date'],
                    'environment' => $m['env'],
                    'level' => strtolower($m['level']),
                    'message' => $m['message'],
                    'extra' => [],
                ];
            } elseif ($current !== null) {
                $current['extra'][] = $line;
            }
        }

        if ($current !== null) {
            $entries[] = $this->finalize($current);
        }

        return collect($entries)->reverse()->values();
    }

    /**
     * @param  array{index: int, datetime: string, environment: string, level: string, message: string, extra: array<int, string>}  $entry
     * @return array{__key: string, datetime: string, environment: string, level: string, message: string, context: string, full: string}
     */
    private function finalize(array $entry): array
    {
        $context = trim(implode("\n", $entry['extra']));

        return [
            '__key' => (string) $entry['index'],
            'datetime' => $entry['datetime'],
            'environment' => $entry['environment'],
            'level' => $entry['level'],
            'message' => $entry['message'],
            'context' => $context,
            'full' => trim($entry['message']."\n".$context),
        ];
    }
}
