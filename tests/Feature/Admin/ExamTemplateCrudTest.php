<?php

use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Models\User;

it('permet à un administrateur de créer un examen avec des résultats structurés', function () {
    $admin = User::factory()->admin()->create();
    $hospital = Hospital::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.hospitals.exam-templates.store', $hospital), [
        'title' => 'Radiographie du Poignet',
        'heading' => 'Compte Rendu de Radiographie du Poignet',
        'modality' => 'radiographie',
        'requires_side' => '1',
        'technique' => 'Clichés standard du poignet',
        'results_text' => "Ligne un.\nLigne deux.\n\nLigne trois.",
        'conclusion' => 'Radiographie normale.',
    ]);

    $response->assertRedirect(route('admin.hospitals.exam-templates.index', $hospital));

    $exam = ExamTemplate::where('title', 'Radiographie du Poignet')->firstOrFail();
    expect($exam->requires_side)->toBeTrue()
        ->and($exam->results)->toHaveCount(3)
        ->and($exam->results[0])->toBe(['text' => 'Ligne un.', 'abnormal' => false, 'heading' => false]);
});

it('empêche deux examens de même titre pour un même hôpital', function () {
    $admin = User::factory()->admin()->create();
    $hospital = Hospital::factory()->create();
    ExamTemplate::factory()->for($hospital)->create(['title' => 'Radiographie du Thorax']);

    $this->actingAs($admin)
        ->post(route('admin.hospitals.exam-templates.store', $hospital), [
            'title' => 'Radiographie du Thorax',
            'heading' => 'Compte Rendu de Radiographie du Thorax',
        ])
        ->assertSessionHasErrors('title');
});

it('désactive un examen par soft delete', function () {
    $admin = User::factory()->admin()->create();
    $hospital = Hospital::factory()->create();
    $exam = ExamTemplate::factory()->for($hospital)->create();

    $this->actingAs($admin)->delete(route('admin.hospitals.exam-templates.destroy', [$hospital, $exam]));

    $this->assertSoftDeleted('exam_templates', ['id' => $exam->id]);
});

it('interdit la gestion des examens à une secrétaire', function () {
    $secretaire = User::factory()->secretaire()->create();
    $hospital = Hospital::factory()->create();

    $this->actingAs($secretaire)
        ->get(route('admin.hospitals.exam-templates.index', $hospital))
        ->assertForbidden();
});
