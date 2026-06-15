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

    /*
    |--------------------------------------------------------------------------
    | Layouts de página
    |--------------------------------------------------------------------------
    | Cada layout é a grelha base da página, expressa em colunas de 12. Os
    | blocos são distribuídos pelas colunas (campo "column" de cada instância).
    | Em mobile as colunas empilham (col-span-12).
    */

    'layouts' => [
        'full' => ['label' => 'Largura total', 'columns' => [12]],
        '6-6' => ['label' => 'Duas colunas (6 + 6)', 'columns' => [6, 6]],
        '8-4' => ['label' => 'Conteúdo + sidebar (8 + 4)', 'columns' => [8, 4]],
        '4-8' => ['label' => 'Sidebar + conteúdo (4 + 8)', 'columns' => [4, 8]],
        '4-4-4' => ['label' => 'Três colunas (4 + 4 + 4)', 'columns' => [4, 4, 4]],
        '3-3-3-3' => ['label' => 'Quatro colunas (3 + 3 + 3 + 3)', 'columns' => [3, 3, 3, 3]],
    ],
];
