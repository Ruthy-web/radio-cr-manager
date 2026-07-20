<?php

use App\Models\Hospital;
use App\Models\Report;
use App\Models\User;

it('affiche les statistiques de base à tout utilisateur authentifié', function () {
    $radiologue = User::factory()->create();
    Report::factory()->count(2)->create(['status' => 'brouillon']);
    Report::factory()->create(['status' => 'signe']);

    $response = $this->actingAs($radiologue)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('Comptes rendus');
    $response->assertDontSee("Journal d'audit", false);
});

it('affiche les liens d’administration et le journal récent à un administrateur', function () {
    $admin = User::factory()->admin()->create();
    Hospital::factory()->create(['active' => true]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee("Journal d'audit", false);
});

it('redirige un visiteur non authentifié vers la connexion', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});
