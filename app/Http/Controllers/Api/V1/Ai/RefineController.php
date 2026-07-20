<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ai\RefineRequest;
use App\Services\Ai\AiUsageLogger;
use App\Services\Ai\AnthropicClient;
use App\Services\Ai\DictationRefinerService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Proxy de raffinage IA d'une dictée (F4) : la clé Anthropic reste côté
 * serveur (R4). Aucun contenu dicté ni généré n'est journalisé (R3).
 */
class RefineController extends Controller
{
    public function __construct(
        private readonly DictationRefinerService $refiner,
        private readonly AiUsageLogger $usage,
        private readonly AnthropicClient $client,
    ) {}

    public function refine(RefineRequest $request): JsonResponse
    {
        $startedAt = microtime(true);
        $model = $this->client->defaultModel();

        try {
            $result = $this->refiner->refine(
                $request->input('results', []),
                $request->input('conclusion'),
                $request->string('dictation')->toString(),
            );

            $this->usage->record(
                $request->user(),
                'refine',
                'anthropic',
                $result['model'],
                success: true,
                httpStatus: 200,
                durationMs: (microtime(true) - $startedAt) * 1000,
            );

            return response()->json([
                'results' => $result['results'],
                'conclusion' => $result['conclusion'],
            ]);
        } catch (Throwable $e) {
            $this->usage->record(
                $request->user(),
                'refine',
                'anthropic',
                $model,
                success: false,
                httpStatus: null,
                durationMs: (microtime(true) - $startedAt) * 1000,
                errorMessage: $e->getMessage(),
            );

            return response()->json(['message' => $e->getMessage()], 502);
        }
    }
}
