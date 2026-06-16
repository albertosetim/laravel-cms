<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Defaults das general settings
    |--------------------------------------------------------------------------
    | Valores iniciais e fallback do bucket geral, lidos do .env. A DB começa
    | vazia: enquanto um campo não for gravado no painel, vale o default daqui.
    | Segredos NÃO vivem aqui — só configuração não-sensível.
    */

    'defaults' => [
        'site_name' => env('SETTINGS_SITE_NAME', 'web-crossing CMS'),
        'contact_email' => env('SETTINGS_CONTACT_EMAIL', 'info@web-crossing.com'),
        'contact_phone' => env('SETTINGS_CONTACT_PHONE', '+43 512 206567'),
        'timezone' => env('SETTINGS_TIMEZONE', env('APP_TIMEZONE', 'Europe/Vienna')),
        'maintenance_mode' => (bool) env('SETTINGS_MAINTENANCE_MODE', false),
    ],

];
