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
    }
}
