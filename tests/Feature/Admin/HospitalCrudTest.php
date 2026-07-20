<?php

use App\Models\Hospital;
use App\Models\User;

it('permet à un administrateur de créer un hôpital', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.hospitals.store'), [
        'name' => 'Nouvel Hôpital Test',
        'slug' => 'nouvel-hopital-test',
        'radiologist_name' => 'Dr Test',
        'colors' => ['primary' => '#123456'],
    ]);

    $response->assertRedirect(route('admin.hospitals.index'));
    $this->assertDatabaseHas('hospitals', ['slug' => 'nouvel-hopital-test', 'active' => true]);
});

it('refuse la création d’un hôpital à un rôle non administrateur', function () {
    $radiologue = User::factory()->create();

    $this->actingAs($radiologue)
        ->post(route('admin.hospitals.store'), ['name' => 'X', 'slug' => 'x'])
        ->assertForbidden();
});

it('exige un slug unique', function () {
    Hospital::factory()->create(['slug' => 'deja-pris']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.hospitals.store'), ['name' => 'Doublon', 'slug' => 'deja-pris'])
        ->assertSessionHasErrors('slug');
});

it('désactive un hôpital par soft delete plutôt qu’une suppression physique', function () {
    $admin = User::factory()->admin()->create();
    $hospital = Hospital::factory()->create();

    $this->actingAs($admin)->delete(route('admin.hospitals.destroy', $hospital));

    $this->assertSoftDeleted('hospitals', ['id' => $hospital->id]);
    expect(Hospital::withTrashed()->find($hospital->id)->active)->toBeFalse();
});

it('permet de réactiver un hôpital désactivé', function () {
    $admin = User::factory()->admin()->create();
    $hospital = Hospital::factory()->create();
    $hospital->update(['active' => false]);
    $hospital->delete();

    $this->actingAs($admin)->post(route('admin.hospitals.restore', $hospital));

    $fresh = Hospital::find($hospital->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->active)->toBeTrue();
});

it('met à jour un hôpital existant', function () {
    $admin = User::factory()->admin()->create();
    $hospital = Hospital::factory()->create(['name' => 'Ancien Nom']);

    $this->actingAs($admin)->put(route('admin.hospitals.update', $hospital), [
        'name' => 'Nouveau Nom',
        'slug' => $hospital->slug,
    ]);

    expect($hospital->fresh()->name)->toBe('Nouveau Nom');
});
