<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modo manutenção do frontend público. Quando ligado nas general settings, mostra
 * uma página 503 a visitantes anónimos; utilizadores autenticados passam (para poderem
 * pré-visualizar). O /admin não usa este middleware.
 */
class EnsureSiteIsLive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Settings::general()->get('maintenance_mode') && ! $request->user()) {
            return response()->view('maintenance', status: 503);
        }

        return $next($request);
    }
}
