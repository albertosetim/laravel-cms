<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    | Locales ativos do site público e locale default. Config, não DB (G3):
    | o routing precisa disto antes de qualquer query.
    */

    'locales' => ['de', 'en'],
    'default_locale' => 'de',

    /*
    |--------------------------------------------------------------------------
    | Slugs reservados
    |--------------------------------------------------------------------------
    | Recusados na validação de páginas de primeiro nível — não confiar só
    | na ordem de registo das rotas.
    */

    'reserved_slugs' => ['admin', 'api', 'storage', 'livewire', 'vendor', 'build'],

    /*
    |--------------------------------------------------------------------------
    | Slug da homepage
    |--------------------------------------------------------------------------
    | A página raiz com este slug é servida em /{locale} sem o slug no URL.
    */

    'home_slug' => 'home',

    'revisions' => [
        // Revisions publicadas mantidas por página (poda via comando agendado).
        'keep' => 20,
    ],

    'plugins' => [
        // Toggle de plugins no admin. Só para single-server com FS gravável.
        // Default (false): enable/disable é deploy-time via artisan (G1).
        'runtime_toggle' => env('CMS_PLUGINS_RUNTIME_TOGGLE', false),

        // Ficheiro de cache lido no boot (G3). Gerado por cms:plugins:sync.
        'cache_file' => 'cache/cms-plugins.php',
    ],

    'blocks' => [
        // Manifesto da paleta de blocos, gerado por cms:build e committed (G5).
        'manifest' => 'data/blocks.json',
    ],
];
