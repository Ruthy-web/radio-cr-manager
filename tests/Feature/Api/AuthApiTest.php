<?php

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

it('délivre un jeton Sanctum pour des identifiants valides sans 2FA', function () {
    User::factory()->create([
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
        'two_factor_enabled' => false,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
    ]);

    $response->assertOk()
        ->assertJsonPath('two_factor_required', false)
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
});

it('refuse un jeton pour un mot de passe invalide', function () {
    User::factory()->create([
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'radio@example.com',
        'password' => 'faux-mot-de-passe',
    ])->assertUnprocessable();
});

it('renvoie un défi 2FA puis délivre un jeton après vérification du code', function () {
    Mail::fake();

    User::factory()->create([
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
        'two_factor_enabled' => true,
    ]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
    ]);

    $login->assertOk()->assertJsonPath('two_factor_required', true);
    $challengeToken = $login->json('challenge_token');

    $sentCode = null;
    Mail::assertSent(TwoFactorCodeMail::class, function ($mail) use (&$sentCode) {
        $sentCode = $mail->code;

        return true;
    });

    $verify = $this->postJson('/api/v1/auth/2fa/verify', [
        'challenge_token' => $challengeToken,
        'code' => $sentCode,
    ]);

    $verify->assertOk()->assertJsonStructure(['token', 'user']);
});

it('protège le heartbeat par authentification et le révèle le rôle courant', function () {
    $user = User::factory()->create();
    $token = $user->createToken('pwa')->plainTextToken;

    $this->getJson('/api/v1/heartbeat')->assertUnauthorized();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/heartbeat')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);
});

it('révoque le jeton après 15 minutes d’inactivité', function () {
    $user = User::factory()->create();
    $token = $user->createToken('pwa');
    $plainTextToken = $token->plainTextToken;

    // Simule un premier appel il y a 20 minutes (au-delà du seuil de 15 min).
    Cache::put("token_activity:{$token->accessToken->id}", now()->subMinutes(20));

    $response = $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/v1/heartbeat');

    $response->assertUnauthorized();
    expect($user->tokens()->count())->toBe(0);
});

it('révoque le jeton à la déconnexion', function () {
    $user = User::factory()->create();
    $token = $user->createToken('pwa')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});
