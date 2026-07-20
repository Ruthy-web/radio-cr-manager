<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ai\DraftRequest;
use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Services\Ai\AiUsageLogger;
use App\Services\Ai\AnthropicClient;
use App\Services\Ai\ReportDraftService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Proxy de génération IA d'un compte rendu complet (F4) : la clé Anthropic
 * reste côté serveur (R4). Aucun contenu dicté/généré n'est journalisé (R3).
 */
class DraftController extends Controller
{
    public function __construct(
        private readonly ReportDraftService $drafter,
        private readonly AiUsageLogger $usage,
        private readonly AnthropicClient $client,
    ) {}

    public function draft(DraftRequest $request): JsonResponse
    {
        $startedAt = microtime(true);
        $model = $this->client->defaultModel();

        try {
            $hospital = Hospital::findOrFail($request->input('hospital_id'));
            $examTemplate = $request->input('exam_template_id')
                ? ExamTemplate::find($request->input('exam_template_id'))
                : null;

            $result = $this->drafter->draft(
                $request->string('prompt')->toString(),
                $hospital,
                $examTemplate,
                $request->input('patient', []),
                $request->input('attachments', []),
            );

            $this->usage->record(
                $request->user(),
                'draft',
                'anthropic',
                $result['model'],
                success: true,
                httpStatus: 200,
                durationMs: (microtime(true) - $startedAt) * 1000,
            );

            return response()->json([
                'heading' => $result['heading'],
                'technique' => $result['technique'],
                'results' => $result['results'],
                'conclusion' => $result['conclusion'],
            ]);
        } catch (Throwable $e) {
            $this->usage->record(
                $request->user(),
                'draft',
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
