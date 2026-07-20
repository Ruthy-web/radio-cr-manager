<?php

use App\Models\AiUsageLog;
use App\Models\ApiCredential;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    ApiCredential::create(['provider' => 'anthropic', 'api_key' => 'sk-ant-test']);
});

it('lit un bulletin photographié et journalise l’usage sans le contenu', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"lastName":"NGALLE","firstName":"Jean","age":"41 ans","dob":"","sex":"M","record":"686/DN","doctor":"Dr Mballa","exam":"","side":"","rawText":"Mr NGALLE Jean 41 ans"}']],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    $user = User::factory()->create();
    $bulletin = UploadedFile::fake()->image('bulletin.jpg', 800, 600);

    $response = $this->withHeaders(bearer($user))->postJson(route('api.v1.ai.bulletin'), ['bulletin' => $bulletin]);

    $response->assertOk()->assertJson([
        'lastName' => 'NGALLE',
        'firstName' => 'Jean',
        'sex' => 'M',
        'record' => '686/DN',
    ]);

    $log = AiUsageLog::firstOrFail();
    expect($log->endpoint)->toBe('bulletin')
        ->and($log->success)->toBeTrue()
        ->and(json_encode($log->toArray()))->not->toContain('NGALLE');
});

it('refuse une lecture de bulletin sans authentification', function () {
    $this->postJson(route('api.v1.ai.bulletin'), ['bulletin' => UploadedFile::fake()->image('x.jpg')])
        ->assertUnauthorized();
});

it('rejette un fichier qui n’est ni une image ni un PDF', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))->postJson(route('api.v1.ai.bulletin'), [
        'bulletin' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ])->assertJsonValidationErrors('bulletin');
});
