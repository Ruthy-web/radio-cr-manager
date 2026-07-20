<?php

use App\Models\ApiCredential;
use App\Services\Ai\ApiCredentialResolver;

it('donne la priorité à la clé enregistrée en base sur la variable d’environnement', function () {
    config(['services.groq.key' => 'env_fallback_key']);
    ApiCredential::create(['provider' => 'groq', 'api_key' => 'db_key']);

    expect(app(ApiCredentialResolver::class)->resolve('groq'))->toBe('db_key');
});

it('se replie sur la variable d’environnement si aucune clé n’est en base', function () {
    config(['services.anthropic.key' => 'env_fallback_key']);

    expect(app(ApiCredentialResolver::class)->resolve('anthropic'))->toBe('env_fallback_key');
});

it('retourne null si rien n’est configuré', function () {
    config(['services.groq.key' => null]);

    expect(app(ApiCredentialResolver::class)->resolve('groq'))->toBeNull()
        ->and(app(ApiCredentialResolver::class)->isConfigured('groq'))->toBeFalse();
});
