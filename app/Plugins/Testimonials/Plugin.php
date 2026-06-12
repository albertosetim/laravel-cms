<?php

namespace App\Plugins\Testimonials;

use App\Cms\Plugins\PluginProvider;

/**
 * Plugin de exemplo: prova o ciclo completo — discover → enable → sync →
 * cms:build → blocos na paleta → render.
 */
class Plugin extends PluginProvider
{
    public function slug(): string
    {
        return 'testimonials';
    }

    public function name(): string
    {
        return 'Testimonials';
    }
}
