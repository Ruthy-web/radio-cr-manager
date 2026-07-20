<?php

use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->hospital = Hospital::factory()->create();
    $this->exam = ExamTemplate::factory()->for($this->hospital)->create();
});

function syncPayload(string $clientUuid, Hospital $hospital, array $overrides = []): array
{
    return array_merge([
        'client_uuid' => $clientUuid,
        'hospital_id' => $hospital->id,
        'patient_name' => 'Jean Essomba',
        'content' => ['heading' => 'Compte Rendu', 'results' => [], 'conclusion' => ''],
    ], $overrides);
}

it('crée un compte rendu depuis un envoi de la PWA', function () {
    $uuid = (string) Str::uuid();

    $response = $this->withHeaders(bearer($this->user))->postJson(route('api.v1.reports.sync.push'), [
        'reports' => [syncPayload($uuid, $this->hospital)],
    ]);

    $response->assertOk()->assertJsonPath('results.0.outcome', 'created');

    $report = Report::where('client_uuid', $uuid)->firstOrFail();
    expect($report->patient_name)->toBe('Jean Essomba')
        ->and($report->user_id)->toBe($this->user->id);
});

it('est idempotent : renvoyer le même client_uuid ne crée pas de doublon', function () {
    $uuid = (string) Str::uuid();
    $payload = ['reports' => [syncPayload($uuid, $this->hospital)]];

    $this->withHeaders(bearer($this->user))->postJson(route('api.v1.reports.sync.push'), $payload);
    $response = $this->withHeaders(bearer($this->user))->postJson(route('api.v1.reports.sync.push'), $payload);

    $response->assertOk();
    expect(Report::where('client_uuid', $uuid)->count())->toBe(1);
});

it('applique une mise à jour plus récente que l’état serveur', function () {
    $report = Report::factory()->for($this->hospital)->create(['patient_name' => 'Ancien Nom']);

    $response = $this->withHeaders(bearer($this->user))->postJson(route('api.v1.reports.sync.push'), [
        'reports' => [syncPayload($report->client_uuid, $this->hospital, [
            'patient_name' => 'Nouveau Nom',
            'content' => ['heading' => 'x', 'results' => [], 'conclusion' => ''],
            'updated_at' => now()->addMinute()->toIso8601String(),
        ])],
    ]);

    $response->assertJsonPath('results.0.outcome', 'updated');
    expect($report->fresh()->patient_name)->toBe('Nouveau Nom');
});

it('ignore un envoi obsolète plus ancien que l’état serveur (dernier écrivain gagnant)', function () {
    $report = Report::factory()->for($this->hospital)->create(['patient_name' => 'Nom Serveur Actuel']);

    $response = $this->withHeaders(bearer($this->user))->postJson(route('api.v1.reports.sync.push'), [
        'reports' => [syncPayload($report->client_uuid, $this->hospital, [
            'patient_name' => 'Nom Périmé',
            'updated_at' => $report->updated_at->subMinutes(5)->toIso8601String(),
        ])],
    ]);

    $response->assertJsonPath('results.0.outcome', 'unchanged');
    expect($report->fresh()->patient_name)->toBe('Nom Serveur Actuel');
});

it('refuse de réanimer silencieusement un compte rendu archivé', function () {
    $report = Report::factory()->for($this->hospital)->create();
    $report->delete();

    $response = $this->withHeaders(bearer($this->user))->postJson(route('api.v1.reports.sync.push'), [
        'reports' => [syncPayload($report->client_uuid, $this->hospital, [
            'updated_at' => now()->addMinute()->toIso8601String(),
        ])],
    ]);

    $response->assertJsonPath('results.0.outcome', 'conflict');
    expect(Report::withTrashed()->find($report->id)->trashed())->toBeTrue();
});

it('rejette un envoi sans hôpital ni contenu', function () {
    $this->withHeaders(bearer($this->user))->postJson(route('api.v1.reports.sync.push'), [
        'reports' => [['client_uuid' => (string) Str::uuid()]],
    ])->assertJsonValidationErrors(['reports.0.hospital_id', 'reports.0.content']);
});

it('refuse un envoi sans authentification', function () {
    $this->postJson(route('api.v1.reports.sync.push'), ['reports' => []])->assertUnauthorized();
});

it('récupère les comptes rendus modifiés depuis un horodatage donné', function () {
    $old = Report::factory()->for($this->hospital)->create();
    Report::where('id', $old->id)->update(['updated_at' => now()->subDays(2)]);

    $recent = Report::factory()->for($this->hospital)->create();
    Report::where('id', $recent->id)->update(['updated_at' => now()->subMinute()]);

    $response = $this->withHeaders(bearer($this->user))
        ->getJson(route('api.v1.reports.sync.pull', ['since' => now()->subHour()->toIso8601String()]));

    $response->assertOk();
    $uuids = collect($response->json('reports'))->pluck('client_uuid');

    expect($uuids)->toContain($recent->client_uuid)
        ->and($uuids)->not->toContain($old->client_uuid);
});

it('signale les comptes rendus archivés comme supprimés côté client', function () {
    $report = Report::factory()->for($this->hospital)->create();
    $report->delete();

    $response = $this->withHeaders(bearer($this->user))
        ->getJson(route('api.v1.reports.sync.pull', ['since' => now()->subHour()->toIso8601String()]));

    $item = collect($response->json('reports'))->firstWhere('client_uuid', $report->client_uuid);
    expect($item['deleted'])->toBeTrue();
});

it('renvoie tout l’historique quand aucun paramètre since n’est fourni', function () {
    Report::factory()->for($this->hospital)->create();

    $response = $this->withHeaders(bearer($this->user))->getJson(route('api.v1.reports.sync.pull'));

    $response->assertOk();
    expect($response->json('reports'))->toHaveCount(1);
});

it('refuse une récupération sans authentification', function () {
    $this->getJson(route('api.v1.reports.sync.pull'))->assertUnauthorized();
});
