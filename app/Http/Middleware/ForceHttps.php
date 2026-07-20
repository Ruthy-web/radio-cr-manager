<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force HTTPS en dehors de l'environnement local (R3 : confidentialité
 * médicale, aucune donnée patient ne doit transiter en clair).
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isSecure() && ! app()->environment('local', 'testing')) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
}
