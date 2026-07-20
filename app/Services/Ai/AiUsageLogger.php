<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Models\User;

/**
 * Journalise l'usage des proxys IA (F4) : uniquement des métadonnées
 * techniques, jamais le contenu dicté ni le texte généré (R3 — ces contenus
 * portent potentiellement des données patient).
 */
class AiUsageLogger
{
    public function record(
        ?User $user,
        string $endpoint,
        string $provider,
        ?string $model,
        bool $success,
        ?int $httpStatus,
        float $durationMs,
        ?string $errorMessage = null,
    ): void {
        AiUsageLog::create([
            'user_id' => $user?->id,
            'endpoint' => $endpoint,
            'provider' => $provider,
            'model' => $model,
            'success' => $success,
            'http_status' => $httpStatus,
            'duration_ms' => (int) round($durationMs),
            'error_message' => $errorMessage ? mb_substr($errorMessage, 0, 255) : null,
            'created_at' => now(),
        ]);
    }
}
