<?php

use App\Models\ApiCredential;
use App\Models\User;

it('permet à un administrateur de configurer les clés API', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->put(route('admin.settings.api-credentials.update'), [
        'groq_api_key' => 'gsk_nouvelle_cle',
        'anthropic_api_key' => 'sk-ant-nouvelle-cle',
    ]);

    $response->assertRedirect(route('admin.settings.api-credentials.edit'));

    expect(ApiCredential::where('provider', 'groq')->first()->api_key)->toBe('gsk_nouvelle_cle')
        ->and(ApiCredential::where('provider', 'anthropic')->first()->api_key)->toBe('sk-ant-nouvelle-cle');
});

it('conserve la clé existante quand le champ est laissé vide', function () {
    $admin = User::factory()->admin()->create();
    ApiCredential::create(['provider' => 'groq', 'api_key' => 'gsk_ancienne_cle']);

    $this->actingAs($admin)->put(route('admin.settings.api-credentials.update'), [
        'groq_api_key' => '',
        'anthropic_api_key' => 'sk-ant-nouvelle-cle',
    ]);

    expect(ApiCredential::where('provider', 'groq')->first()->api_key)->toBe('gsk_ancienne_cle');
});

it('n’affiche jamais une clé déjà enregistrée en clair', function () {
    $admin = User::factory()->admin()->create();
    ApiCredential::create(['provider' => 'groq', 'api_key' => 'gsk_secret_ne_doit_pas_apparaitre']);

    $response = $this->actingAs($admin)->get(route('admin.settings.api-credentials.edit'));

    $response->assertOk()->assertDontSee('gsk_secret_ne_doit_pas_apparaitre');
});

it('interdit l’accès aux clés API à un rôle non administrateur', function () {
    $radiologue = User::factory()->create();

    $this->actingAs($radiologue)->get(route('admin.settings.api-credentials.edit'))->assertForbidden();
});
