<?php

namespace App\Support;

use App\Models\Cms\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Acesso às settings de um owner (bucket geral ou um tipo de conteúdo).
 * Criado via App\Support\Settings::general() / Settings::for().
 */
class SettingsBag
{
    private const GENERAL_CACHE_KEY = 'cms.settings.general';

    public function __construct(
        private readonly string $type,
        private readonly int $id,
        private readonly bool $isGeneral = false,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->stored();

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        if ($this->isGeneral) {
            return config("settings.defaults.{$key}", $default);
        }

        return $default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['settable_type' => $this->type, 'settable_id' => $this->id, 'key' => $key],
            ['value' => $value],
        );

        $this->forgetCache();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function fill(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Substitui o conjunto completo: apaga as chaves ausentes e grava as fornecidas.
     *
     * @param  array<string, mixed>  $values
     */
    public function replace(array $values): void
    {
        $keep = array_keys($values);

        Setting::query()
            ->where('settable_type', $this->type)
            ->where('settable_id', $this->id)
            ->when($keep !== [], fn ($query) => $query->whereNotIn('key', $keep))
            ->delete();

        $this->fill($values);
        $this->forgetCache();
    }

    /**
     * Valores efetivos (DB sobre defaults, no caso geral).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $defaults = $this->isGeneral ? (array) config('settings.defaults', []) : [];

        return array_merge($defaults, $this->stored());
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        if ($this->isGeneral) {
            return Cache::rememberForever(self::GENERAL_CACHE_KEY, fn (): array => $this->query());
        }

        return $this->query();
    }

    /**
     * @return array<string, mixed>
     */
    private function query(): array
    {
        return Setting::query()
            ->where('settable_type', $this->type)
            ->where('settable_id', $this->id)
            ->pluck('value', 'key')
            ->all();
    }

    private function forgetCache(): void
    {
        if ($this->isGeneral) {
            Cache::forget(self::GENERAL_CACHE_KEY);
        }
    }
}
