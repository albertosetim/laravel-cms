<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Ponto de entrada para as settings polimórficas (estilo media).
 *
 *   Settings::general()->get('site_name')
 *   Settings::for(Blog::class)->set('per_page', 12)   // nível-tipo
 *   Settings::for($blog)->get('x')                     // nível-instância (futuro)
 */
class Settings
{
    public const GENERAL_TYPE = 'general';

    public static function general(): SettingsBag
    {
        return new SettingsBag(self::GENERAL_TYPE, 0, isGeneral: true);
    }

    public static function for(string|Model $owner): SettingsBag
    {
        if ($owner instanceof Model) {
            return new SettingsBag($owner->getMorphClass(), (int) $owner->getKey());
        }

        // FQCN de um model → settings ao nível do tipo (settable_id = 0).
        return new SettingsBag((new $owner)->getMorphClass(), 0);
    }
}
