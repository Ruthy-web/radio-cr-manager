<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ai\ChatRequest;
use App\Services\Ai\AiUsageLogger;
use App\Services\Ai\AnthropicClient;
use App\Services\Ai\ChatAssistantService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Proxy de l'assistant de recherche clinique/radiologique (F4). La clé
 * Anthropic reste côté serveur (R4). Ni les questions ni les réponses ne
 * sont journalisées (R3) — seules des métadonnées d'usage le sont.
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly ChatAssistantService $assistant,
        private readonly AiUsageLogger $usage,
        private readonly AnthropicClient $client,
    ) {}

    public function send(ChatRequest $request): JsonResponse
    {
        $startedAt = microtime(true);
        $model = $this->client->defaultModel();

        try {
            $result = $this->assistant->send(
                $request->input('messages'),
                (bool) $request->boolean('use_web'),
            );

            $this->usage->record(
                $request->user(),
                'chat',
                'anthropic',
                $result['model'],
                success: true,
                httpStatus: 200,
                durationMs: (microtime(true) - $startedAt) * 1000,
            );

            return response()->json(['text' => $result['text'], 'sources' => $result['sources']]);
        } catch (Throwable $e) {
            $this->usage->record(
                $request->user(),
                'chat',
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
