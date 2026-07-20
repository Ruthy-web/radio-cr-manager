<?php

use App\Enums\UserRole;
use App\Models\User;

it('permet à un utilisateur actif de se connecter avec des identifiants valides', function () {
    $user = User::factory()->create([
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
        'two_factor_enabled' => false,
    ]);

    $response = $this->post(route('admin.login.store'), [
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user->fresh());
});

it('refuse une connexion avec un mot de passe incorrect', function () {
    User::factory()->create([
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
    ]);

    $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
        'email' => 'radio@example.com',
        'password' => 'mauvais-mot-de-passe',
    ]);

    $response->assertRedirect(route('admin.login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('incrémente les échecs et verrouille le compte après 5 tentatives infructueuses', function () {
    $user = User::factory()->create([
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('admin.login.store'), [
            'email' => 'radio@example.com',
            'password' => 'mauvais-mot-de-passe',
        ]);
    }

    expect($user->fresh()->isLocked())->toBeTrue();

    // Même avec le bon mot de passe, le compte reste verrouillé 15 minutes.
    $response = $this->post(route('admin.login.store'), [
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('refuse la connexion d’un compte désactivé', function () {
    User::factory()->create([
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
        'active' => false,
    ]);

    $response = $this->post(route('admin.login.store'), [
        'email' => 'radio@example.com',
        'password' => 'MotDePasse!2026',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('déconnecte l’utilisateur authentifié', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    $this->assertGuest();
});

it('restreint le tableau de bord aux utilisateurs authentifiés', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

it('applique les rôles applicatifs disponibles', function () {
    $admin = User::factory()->admin()->create();
    $secretaire = User::factory()->secretaire()->create();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($secretaire->role)->toBe(UserRole::Secretaire)
        ->and($admin->hasRole(UserRole::Admin))->toBeTrue()
        ->and($admin->hasRole(UserRole::Secretaire))->toBeFalse();
});
