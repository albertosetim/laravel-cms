<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ \App\Support\Settings::general()->get('site_name') }} — {{ __('Maintenance') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
               background: #0d1117; color: #c9d1d9; }
        .box { text-align: center; padding: 2rem; max-width: 32rem; }
        h1 { font-size: 1.5rem; margin: 0 0 .75rem; color: #fff; }
        p { margin: 0; line-height: 1.6; color: #8b949e; }
    </style>
</head>
<body>
    <div class="box">
        <h1>{{ \App\Support\Settings::general()->get('site_name') }}</h1>
        <p>{{ __('The site is temporarily down for maintenance. Please check back later.') }}</p>
    </div>
</body>
</html>
