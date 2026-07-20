<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité applicatifs (F9) : durcissement contre le clickjacking,
 * le MIME-sniffing et les fuites de referrer sur des données médicales.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        // Note : 'unsafe-eval' est nécessaire au fonctionnement d'Alpine.js
        // (évaluation des expressions x-data/x-on), qui est le choix de
        // stack imposé pour l'admin. Aucun script inline n'est autorisé
        // ('unsafe-inline' volontairement absent de script-src) : tout le JS
        // est servi en fichiers externes versionnés depuis 'self'.
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-eval'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; base-uri 'self'; frame-ancestors 'none'"
        );

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
