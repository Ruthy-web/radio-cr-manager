<?php

use App\Models\AuditLog;
use App\Models\User;

it('liste le journal d’audit pour un administrateur', function () {
    $admin = User::factory()->admin()->create();
    AuditLog::create(['user_id' => $admin->id, 'action' => 'connexion_reussie', 'created_at' => now()]);
    AuditLog::create(['user_id' => $admin->id, 'action' => 'hopital_cree', 'created_at' => now()]);

    $response = $this->actingAs($admin)->get(route('admin.audit.index'));

    $response->assertOk();
    $response->assertSee('connexion_reussie');
    $response->assertSee('hopital_cree');
});

it('filtre le journal par action', function () {
    $admin = User::factory()->admin()->create();
    AuditLog::create(['user_id' => $admin->id, 'action' => 'connexion_reussie', 'created_at' => now()]);
    AuditLog::create(['user_id' => $admin->id, 'action' => 'hopital_cree', 'created_at' => now()]);

    $response = $this->actingAs($admin)->get(route('admin.audit.index', ['action' => 'connexion']));

    $response->assertOk();
    $response->assertSee('connexion_reussie');
    $response->assertDontSee('hopital_cree');
});

it('interdit le journal d’audit à un rôle non administrateur', function () {
    $radiologue = User::factory()->create();

    $this->actingAs($radiologue)->get(route('admin.audit.index'))->assertForbidden();
});

it('redirige un visiteur non authentifié vers la connexion', function () {
    $this->get(route('admin.audit.index'))->assertRedirect(route('admin.login'));
});
