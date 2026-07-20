<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:admin'])
        ->get('/test-reserve-admin', fn () => response('ok'));
});

it('autorise un administrateur à accéder à une route réservée', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/test-reserve-admin')->assertOk();
});

it('interdit à un rôle non autorisé d’accéder à une route réservée', function () {
    $secretaire = User::factory()->secretaire()->create();

    $this->actingAs($secretaire)->get('/test-reserve-admin')->assertForbidden();
});

it('redirige un visiteur non authentifié vers la connexion', function () {
    $this->get('/test-reserve-admin')->assertRedirect(route('admin.login'));
});
