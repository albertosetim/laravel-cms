<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Helpers dos idiomas do site (config cms.locales / default_locale).
 */
class Locales
{
    /**
     * @return array<string, string>  ['de' => 'Deutsch', 'en' => 'English']
     */
    public static function options(): array
    {
        return Collection::make(config('cms.locales'))
            ->mapWithKeys(fn (string $locale): array => [$locale => self::label($locale)])
            ->all();
    }

    public static function label(string $locale): string
    {
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayLanguage($locale, $locale);

            if (filled($name)) {
                return ucfirst($name);
            }
        }

        return strtoupper($locale);
    }

    public static function default(): string
    {
        return (string) config('cms.default_locale');
    }
}
