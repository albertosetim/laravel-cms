<?php

namespace App\Models\Concerns;

use App\Support\Settings;
use App\Support\SettingsBag;

/**
 * Dá ao model acesso às suas settings (estilo media). Ao nível do tipo:
 *
 *   Blog::settings()->get('per_page', 10);
 *   Blog::settings()->set('per_page', 25);
 */
trait HasSettings
{
    public static function settings(): SettingsBag
    {
        return Settings::for(static::class);
    }
}
