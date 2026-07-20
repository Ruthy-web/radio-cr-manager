<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Synchronisation de la PWA (F6). Le backend n'est qu'une couche de
 * synchronisation, jamais un point de passage obligé (R5) : la PWA continue
 * de fonctionner et de stocker localement hors ligne ; cette API rattrape
 * l'état serveur au retour du réseau.
 *
 * Idempotence : chaque compte rendu créé côté PWA porte un `client_uuid`
 * généré sur l'appareil (jamais régénéré), qui sert de clé d'upsert — un
 * même envoi rejoué (ex. après coupure réseau juste après la réponse) ne
 * crée jamais de doublon.
 */
class ReportSyncService
{
    private const PULL_LIMIT = 500;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @return array<int, array<string, mixed>>
     */
    public function push(User $user, array $payloads): array
    {
        return array_map(fn (array $payload) => $this->pushOne($user, $payload), $payloads);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function pushOne(User $user, array $payload): array
    {
        $existing = Report::withTrashed()->where('client_uuid', $payload['client_uuid'])->first();

        // Un compte rendu archivé côté serveur ne doit jamais être réanimé
        // silencieusement par un renvoi tardif de l'appareil (archivage
        // volontaire, F3) : l'appareil doit l'effacer localement, pas le
        // faire revivre avec un contenu potentiellement obsolète.
        if ($existing && $existing->trashed()) {
            return $this->outcome($existing, 'conflict');
        }

        $incomingUpdatedAt = isset($payload['updated_at']) ? Carbon::parse($payload['updated_at']) : null;

        // Rejeu idempotent ou écho tardif d'un appareil : le serveur a déjà
        // un état identique ou plus récent, on ne perd rien à ignorer.
        if ($existing && $incomingUpdatedAt && $incomingUpdatedAt->lessThanOrEqualTo($existing->updated_at)) {
            return $this->outcome($existing, 'unchanged');
        }

        $attributes = [
            'hospital_id' => $payload['hospital_id'],
            'exam_template_id' => $payload['exam_template_id'] ?? null,
            'patient_name' => $payload['patient_name'] ?? null,
            'patient_age' => $payload['patient_age'] ?? null,
            'patient_sex' => $payload['patient_sex'] ?? null,
            'file_number' => $payload['file_number'] ?? null,
            'prescriber' => $payload['prescriber'] ?? null,
            'exam_date' => $payload['exam_date'] ?? null,
            'content' => $payload['content'],
            'status' => $payload['status'] ?? 'brouillon',
        ];

        if ($existing) {
            $existing->update($attributes);
            $this->audit->log('compte_rendu_synchronise_modifie', $user, $existing);

            return $this->outcome($existing->refresh(), 'updated');
        }

        $report = Report::create([
            'client_uuid' => $payload['client_uuid'],
            'user_id' => $user->id,
            ...$attributes,
        ]);
        $this->audit->log('compte_rendu_synchronise_cree', $user, $report);

        return $this->outcome($report, 'created');
    }

    private function outcome(Report $report, string $outcome): array
    {
        return [
            'client_uuid' => $report->client_uuid,
            'id' => $report->id,
            'outcome' => $outcome,
            'updated_at' => $report->updated_at->toIso8601String(),
        ];
    }

    /**
     * @return array{reports: array<int, array<string, mixed>>, has_more: bool, server_time: string}
     */
    public function pull(?string $since): array
    {
        $sinceDate = $since ? Carbon::parse($since) : CarbonImmutable::createFromTimestamp(0);

        $reports = Report::withTrashed()
            ->with(['hospital', 'examTemplate'])
            ->where('updated_at', '>', $sinceDate)
            ->orderBy('updated_at')
            ->limit(self::PULL_LIMIT)
            ->get();

        return [
            'reports' => $reports->map(fn (Report $report) => $this->toSyncArray($report))->all(),
            'has_more' => $reports->count() >= self::PULL_LIMIT,
            'server_time' => now()->toIso8601String(),
        ];
    }

    private function toSyncArray(Report $report): array
    {
        return [
            'client_uuid' => $report->client_uuid,
            'id' => $report->id,
            'hospital_id' => $report->hospital_id,
            'exam_template_id' => $report->exam_template_id,
            'patient_name' => $report->patient_name,
            'patient_age' => $report->patient_age,
            'patient_sex' => $report->patient_sex,
            'file_number' => $report->file_number,
            'prescriber' => $report->prescriber,
            'exam_date' => $report->exam_date?->format('Y-m-d'),
            'content' => $report->content,
            'status' => $report->status->value,
            'updated_at' => $report->updated_at->toIso8601String(),
            'deleted' => $report->trashed(),
        ];
    }
}
