<?php

use App\Models\AiUsageLog;
use App\Models\ApiCredential;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    ApiCredential::create(['provider' => 'anthropic', 'api_key' => 'sk-ant-test']);
});

it('répond à une question clinique et journalise l’usage sans le contenu', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Le protocole recommandé est...']],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->withHeaders(bearer($user))->postJson(route('api.v1.ai.chat'), [
        'messages' => [['role' => 'user', 'content' => 'Protocole TDM colique néphrétique ?']],
        'use_web' => false,
    ]);

    $response->assertOk()->assertJson(['text' => 'Le protocole recommandé est...', 'sources' => []]);

    Http::assertSent(fn ($request) => ! isset($request->data()['tools']));

    $log = AiUsageLog::firstOrFail();
    expect($log->endpoint)->toBe('chat')
        ->and($log->success)->toBeTrue()
        ->and(json_encode($log->toArray()))->not->toContain('protocole recommandé');
});

it('active la recherche web et remonte les sources citées', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => 'Réponse sourcée.', 'citations' => [['url' => 'https://exemple.org/a', 'title' => 'Source A']]],
            ],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->withHeaders(bearer($user))->postJson(route('api.v1.ai.chat'), [
        'messages' => [['role' => 'user', 'content' => 'Question à jour ?']],
        'use_web' => true,
    ]);

    $response->assertOk()->assertJsonPath('sources.0.url', 'https://exemple.org/a');
    Http::assertSent(fn ($request) => isset($request->data()['tools']));
});

it('refuse une conversation sans authentification', function () {
    $this->postJson(route('api.v1.ai.chat'), ['messages' => [['role' => 'user', 'content' => 'x']]])
        ->assertUnauthorized();
});

it('rejette une requête sans messages', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))->postJson(route('api.v1.ai.chat'), [])
        ->assertJsonValidationErrors('messages');
});
