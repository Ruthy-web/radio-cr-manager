<?php

namespace App\Services\Ai;

use App\Models\ApiCredential;

/**
 * Résout la clé API d'un fournisseur IA (F4) : priorité à la clé saisie et
 * chiffrée en base via l'écran admin « Clés API », repli sur la variable
 * d'environnement si aucune clé n'est encore configurée (R4).
 */
class ApiCredentialResolver
{
    public function resolve(string $provider): ?string
    {
        $stored = ApiCredential::where('provider', $provider)->first()?->api_key;

        if (! empty($stored)) {
            return $stored;
        }

        return match ($provider) {
            'groq' => config('services.groq.key'),
            'anthropic' => config('services.anthropic.key'),
            default => null,
        };
    }

    public function isConfigured(string $provider): bool
    {
        return ! empty($this->resolve($provider));
    }
}
