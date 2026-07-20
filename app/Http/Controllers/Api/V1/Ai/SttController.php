<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ai\SttRequest;
use App\Services\Ai\AiUsageLogger;
use App\Services\Ai\GroqSpeechToTextService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Proxy de transcription vocale (F4) : la clé Groq reste côté serveur (R4),
 * le fichier audio transmis par la PWA n'est jamais stocké (R3).
 */
class SttController extends Controller
{
    public function __construct(
        private readonly GroqSpeechToTextService $stt,
        private readonly AiUsageLogger $usage,
    ) {}

    public function transcribe(SttRequest $request): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $result = $this->stt->transcribe($request->file('audio'), $request->string('language', 'fr')->toString());

            $this->usage->record(
                $request->user(),
                'stt',
                'groq',
                $this->stt->model(),
                success: true,
                httpStatus: 200,
                durationMs: (microtime(true) - $startedAt) * 1000,
            );

            return response()->json($result);
        } catch (Throwable $e) {
            $this->usage->record(
                $request->user(),
                'stt',
                'groq',
                $this->stt->model(),
                success: false,
                httpStatus: null,
                durationMs: (microtime(true) - $startedAt) * 1000,
                errorMessage: $e->getMessage(),
            );

            return response()->json(['message' => $e->getMessage()], 502);
        }
    }
}
