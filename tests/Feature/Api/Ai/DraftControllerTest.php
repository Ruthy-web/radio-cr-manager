<?php

use App\Models\AiUsageLog;
use App\Models\ApiCredential;
use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    ApiCredential::create(['provider' => 'anthropic', 'api_key' => 'sk-ant-test']);
    $this->hospital = Hospital::factory()->create();
    $this->exam = ExamTemplate::factory()->for($this->hospital)->create(['title' => 'Échographie abdominale']);
});

it('génère un compte rendu complet via Anthropic à partir d’une demande libre', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"heading":"COMPTE RENDU","technique":"Sonde convexe.","results":["Foie normal."],"conclusion":"Examen normal."}']],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->withHeaders(bearer($user))->postJson(route('api.v1.ai.draft'), [
        'prompt' => 'échographie abdominale normale',
        'hospital_id' => $this->hospital->id,
        'exam_template_id' => $this->exam->id,
        'patient' => ['age' => '40', 'sex' => 'F'],
    ]);

    $response->assertOk()->assertJson([
        'heading' => 'COMPTE RENDU',
        'technique' => 'Sonde convexe.',
        'results' => ['Foie normal.'],
        'conclusion' => 'Examen normal.',
    ]);

    expect(AiUsageLog::firstOrFail())
        ->endpoint->toBe('draft')
        ->provider->toBe('anthropic')
        ->success->toBeTrue();
});

it('retente automatiquement après une réponse tronquée (max_tokens) puis réussit', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => '{"heading":"COMPTE RENDU","results":["Foie nor']], 'stop_reason' => 'max_tokens'], 200)
            ->push(['content' => [['type' => 'text', 'text' => '{"heading":"COMPTE RENDU","results":["Foie normal."],"conclusion":"Normal."}']], 'stop_reason' => 'end_turn'], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->withHeaders(bearer($user))->postJson(route('api.v1.ai.draft'), [
        'prompt' => 'échographie abdominale normale',
        'hospital_id' => $this->hospital->id,
    ]);

    $response->assertOk()->assertJson(['results' => ['Foie normal.'], 'conclusion' => 'Normal.']);
    Http::assertSentCount(2);
});

it('refuse une génération sans authentification', function () {
    $this->postJson(route('api.v1.ai.draft'), [
        'prompt' => 'texte',
        'hospital_id' => $this->hospital->id,
    ])->assertUnauthorized();
});

it('rejette une requête sans prompt ni hôpital', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))->postJson(route('api.v1.ai.draft'), [])
        ->assertJsonValidationErrors(['prompt', 'hospital_id']);
});
