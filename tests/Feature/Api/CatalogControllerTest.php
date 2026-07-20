<?php

use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Models\User;

it('renvoie les hôpitaux actifs et leurs examens actifs pour la mise en cache hors ligne', function () {
    $hospital = Hospital::factory()->create(['active' => true]);
    $exam = ExamTemplate::factory()->for($hospital)->create(['active' => true]);
    ExamTemplate::factory()->for($hospital)->create(['active' => false]);

    $inactiveHospital = Hospital::factory()->create(['active' => false]);
    ExamTemplate::factory()->for($inactiveHospital)->create();

    $user = User::factory()->create();

    $response = $this->withHeaders(bearer($user))->getJson(route('api.v1.catalog'));

    $response->assertOk();
    $hospitals = collect($response->json('hospitals'));

    expect($hospitals)->toHaveCount(1);
    $payload = $hospitals->first();
    expect($payload['id'])->toBe($hospital->id)
        ->and($payload['exam_templates'])->toHaveCount(1)
        ->and($payload['exam_templates'][0]['id'])->toBe($exam->id);
});

it('refuse l’accès au catalogue sans authentification', function () {
    $this->getJson(route('api.v1.catalog'))->assertUnauthorized();
});
