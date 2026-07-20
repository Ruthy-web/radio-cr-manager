<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ai\BulletinRequest;
use App\Services\Ai\AiUsageLogger;
use App\Services\Ai\AnthropicClient;
use App\Services\Ai\BulletinVisionService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Proxy de lecture IA vision du bulletin patient photographié (F4). La clé
 * Anthropic reste côté serveur (R4). Ni l'image ni le texte lu ne sont
 * journalisés (R3) — seules des métadonnées d'usage le sont.
 */
class BulletinController extends Controller
{
    public function __construct(
        private readonly BulletinVisionService $vision,
        private readonly AiUsageLogger $usage,
        private readonly AnthropicClient $client,
    ) {}

    public function read(BulletinRequest $request): JsonResponse
    {
        $startedAt = microtime(true);
        $model = $this->client->defaultModel();

        try {
            $result = $this->vision->read($request->file('bulletin'));

            $this->usage->record(
                $request->user(),
                'bulletin',
                'anthropic',
                $result['model'],
                success: true,
                httpStatus: 200,
                durationMs: (microtime(true) - $startedAt) * 1000,
            );

            return response()->json($result);
        } catch (Throwable $e) {
            $this->usage->record(
                $request->user(),
                'bulletin',
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
