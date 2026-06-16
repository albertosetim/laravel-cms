<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define o idioma do painel admin: o locale escolhido pelo utilizador autenticado,
 * ou o locale default do site (config cms) quando não há escolha (ou no login).
 */
class SetPanelLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->preferredLocale() ?? config('cms.default_locale');

        if (in_array($locale, config('cms.locales'), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
