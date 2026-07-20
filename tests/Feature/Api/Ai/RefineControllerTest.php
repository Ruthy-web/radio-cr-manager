<?php

use App\Models\AiUsageLog;
use App\Models\ApiCredential;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    ApiCredential::create(['provider' => 'anthropic', 'api_key' => 'sk-ant-test']);
});

it('intègre une dictée dans les résultats via Anthropic et journalise l’usage sans le contenu', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"results":["Foie augmenté de volume, contours réguliers."],"conclusion":"Hépatomégalie."}']],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->withHeaders(bearer($user))->postJson(route('api.v1.ai.refine'), [
        'results' => ['Foie de morphologie normale.'],
        'conclusion' => 'Examen normal.',
        'dictation' => 'le foie est augmenté de volume',
    ]);

    $response->assertOk()->assertJson([
        'results' => ['Foie augmenté de volume, contours réguliers.'],
        'conclusion' => 'Hépatomégalie.',
    ]);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.anthropic.com/v1/messages'
        && $request->hasHeader('x-api-key', 'sk-ant-test'));

    $log = AiUsageLog::firstOrFail();
    expect($log->endpoint)->toBe('refine')
        ->and($log->provider)->toBe('anthropic')
        ->and($log->success)->toBeTrue()
        ->and(json_encode($log->toArray()))->not->toContain('Hépatomégalie');
});

it('refuse un raffinage sans authentification', function () {
    $this->postJson(route('api.v1.ai.refine'), ['dictation' => 'texte'])->assertUnauthorized();
});

it('rejette une requête sans dictée', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))->postJson(route('api.v1.ai.refine'), [])->assertJsonValidationErrors('dictation');
});

it('retourne une erreur explicite si aucune clé Anthropic n’est configurée', function () {
    ApiCredential::query()->delete();
    config(['services.anthropic.key' => null]);

    $user = User::factory()->create();

    $response = $this->withHeaders(bearer($user))->postJson(route('api.v1.ai.refine'), [
        'results' => [],
        'dictation' => 'texte',
    ]);

    $response->assertStatus(502);
    expect(AiUsageLog::firstOrFail()->success)->toBeFalse();
});
