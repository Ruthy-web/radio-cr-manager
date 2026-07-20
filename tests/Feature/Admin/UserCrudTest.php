<?php

use App\Enums\UserRole;
use App\Models\User;

it('permet à un administrateur de créer un utilisateur', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Nouvelle Secrétaire',
        'email' => 'secretaire2@radio-cr-manager.local',
        'role' => 'secretaire',
        'password' => 'MotDePasse!2026',
    ]);

    $user = User::where('email', 'secretaire2@radio-cr-manager.local')->firstOrFail();
    $response->assertRedirect(route('admin.users.index'));

    expect($user->name)->toBe('Nouvelle Secrétaire')
        ->and($user->role)->toBe(UserRole::Secretaire)
        ->and($user->active)->toBeTrue();
});

it('refuse la création d’un utilisateur à un rôle non administrateur', function () {
    $radiologue = User::factory()->create();

    $this->actingAs($radiologue)->post(route('admin.users.store'), [
        'name' => 'X', 'email' => 'x@example.com', 'role' => 'secretaire', 'password' => 'MotDePasse!2026',
    ])->assertForbidden();
});

it('exige un e-mail unique', function () {
    $admin = User::factory()->admin()->create();
    $existing = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Doublon', 'email' => $existing->email, 'role' => 'secretaire', 'password' => 'MotDePasse!2026',
    ])->assertSessionHasErrors('email');
});

it('met à jour un utilisateur sans changer le mot de passe si le champ est laissé vide', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['name' => 'Ancien Nom']);
    $originalHash = $user->password;

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => 'Nouveau Nom',
        'email' => $user->email,
        'role' => $user->role->value,
        'password' => '',
    ]);

    $user->refresh();
    expect($user->name)->toBe('Nouveau Nom')
        ->and($user->password)->toBe($originalHash);
});

it('change le mot de passe si un nouveau est fourni', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $originalHash = $user->password;

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role->value,
        'password' => 'NouveauMotDePasse!2026',
    ]);

    expect($user->fresh()->password)->not->toBe($originalHash);
});

it('désactive un utilisateur par soft delete et révoque ses jetons plutôt que de le supprimer', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $user->createToken('pwa');

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user));

    $this->assertSoftDeleted('users', ['id' => $user->id]);
    expect($user->tokens()->count())->toBe(0);
});

it('interdit à un administrateur de désactiver son propre compte', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertForbidden();
    $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
});

it('permet de réactiver un utilisateur désactivé', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $user->delete();

    $this->actingAs($admin)->post(route('admin.users.restore', $user->id));

    expect($user->fresh()->trashed())->toBeFalse()
        ->and($user->fresh()->active)->toBeTrue();
});
