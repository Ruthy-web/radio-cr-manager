<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnexion automatique après 15 minutes d'inactivité côté PWA (F1).
 * La PWA doit appeler régulièrement /api/v1/heartbeat pour rester connectée ;
 * sans appel depuis plus de 15 minutes, le jeton Sanctum est révoqué.
 */
class EnsureTokenIsActive
{
    private const INACTIVITY_MINUTES = 15;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $cacheKey = "token_activity:{$token->id}";
            $lastActivity = Cache::get($cacheKey);

            if ($lastActivity !== null && $lastActivity->diffInMinutes(now(), absolute: true) >= self::INACTIVITY_MINUTES) {
                $token->delete();

                return response()->json([
                    'message' => 'Session expirée après 15 minutes d\'inactivité. Veuillez vous reconnecter.',
                ], 401);
            }

            Cache::put($cacheKey, now(), now()->addMinutes(self::INACTIVITY_MINUTES * 2));
        }

        return $next($request);
    }
}
