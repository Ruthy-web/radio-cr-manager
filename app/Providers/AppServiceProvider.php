<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Politique de complexité des mots de passe (F9).
        Password::defaults(fn () => Password::min(10)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised()
        );

        // Limitation du débit sur les tentatives de connexion, en complément
        // du verrouillage de compte (5 échecs -> 15 min) porté par User.
        RateLimiter::for('login', function ($request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(10)->by($email.'|'.$request->ip());
        });

        // Les appels IA (F4) ont un coût réel côté fournisseur : limitation
        // par utilisateur authentifié, en complément du contrôle d'accès.
        RateLimiter::for('ai', function ($request) {
            return Limit::perMinute(20)->by($request->user()?->id ?? $request->ip());
        });

        // Limitation générale de l'API (F9), en défense en profondeur au-delà
        // du contrôle d'accès (heartbeat, catalogue, synchronisation).
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(120)->by($request->user()?->id ?? $request->ip());
        });
    }
}
