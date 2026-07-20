<?php

use App\Models\AiUsageLog;
use App\Models\ApiCredential;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    ApiCredential::create(['provider' => 'groq', 'api_key' => 'gsk_test']);
});

it('transcrit un fichier audio via Groq et journalise l’usage sans le contenu', function () {
    Http::fake([
        'api.groq.com/*' => Http::response(['text' => 'Foie de morphologie normale.'], 200),
    ]);

    $user = User::factory()->create();
    $audio = UploadedFile::fake()->create('vocal.webm', 50, 'audio/webm');

    $response = $this->withHeaders(bearer($user))->postJson(route('api.v1.stt'), ['audio' => $audio]);

    $response->assertOk()->assertJson(['text' => 'Foie de morphologie normale.', 'provider' => 'groq']);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.groq.com/openai/v1/audio/transcriptions'
        && $request->hasHeader('Authorization', 'Bearer gsk_test'));

    $log = AiUsageLog::firstOrFail();
    expect($log->endpoint)->toBe('stt')
        ->and($log->provider)->toBe('groq')
        ->and($log->success)->toBeTrue()
        ->and(json_encode($log->toArray()))->not->toContain('Foie de morphologie normale');
});

it('refuse une transcription sans authentification', function () {
    $audio = UploadedFile::fake()->create('vocal.webm', 50, 'audio/webm');

    $this->postJson(route('api.v1.stt'), ['audio' => $audio])->assertUnauthorized();
});

it('rejette une requête sans fichier audio', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))->postJson(route('api.v1.stt'), [])->assertJsonValidationErrors('audio');
});

it('journalise un échec sans faire planter la requête quand Groq est indisponible', function () {
    Http::fake([
        'api.groq.com/*' => Http::response(['error' => ['message' => 'invalid_api_key']], 401),
    ]);

    $user = User::factory()->create();
    $audio = UploadedFile::fake()->create('vocal.webm', 50, 'audio/webm');

    $response = $this->withHeaders(bearer($user))->postJson(route('api.v1.stt'), ['audio' => $audio]);

    $response->assertStatus(502);
    expect(AiUsageLog::firstOrFail()->success)->toBeFalse();
});
