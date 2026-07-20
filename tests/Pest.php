<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * En-tête Sanctum pour authentifier un appel API (F6) : `actingAs()` seul ne
 * suffit pas sur les routes `auth:sanctum` + `token.active`, qui exigent un
 * vrai jeton (EnsureTokenIsActive lit `currentAccessToken()->id`).
 */
function bearer(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('pwa')->plainTextToken];
}
