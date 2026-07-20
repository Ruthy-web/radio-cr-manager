<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint une route à une liste de rôles. Utilisation : ->middleware('role:admin,radiologue')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Authentification requise.');
        }

        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        if (! $user->hasRole(...$allowed)) {
            abort(403, "Vous n'avez pas les droits nécessaires pour accéder à cette ressource.");
        }

        return $next($request);
    }
}
