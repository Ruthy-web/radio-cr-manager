<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReportSyncPushRequest;
use App\Services\ReportSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de synchronisation de la PWA (F6) : la PWA reste offline-first,
 * cette API n'est qu'une couche de rattrapage au retour du réseau (R5).
 */
class ReportSyncController extends Controller
{
    public function __construct(private readonly ReportSyncService $sync) {}

    /**
     * Envoi des comptes rendus créés/modifiés hors ligne — idempotent par
     * `client_uuid` (F6) : un renvoi après coupure réseau ne duplique rien.
     */
    public function push(ReportSyncPushRequest $request): JsonResponse
    {
        $results = $this->sync->push($request->user(), $request->validated('reports'));

        return response()->json([
            'results' => $results,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Récupération des comptes rendus créés/modifiés/archivés côté serveur
     * depuis `since`, pour que la PWA rattrape son stockage local hors ligne.
     */
    public function pull(Request $request): JsonResponse
    {
        $request->validate(['since' => ['nullable', 'date']]);

        return response()->json($this->sync->pull($request->query('since')));
    }
}
