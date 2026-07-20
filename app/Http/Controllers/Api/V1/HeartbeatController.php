<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint appelé périodiquement par la PWA pour signaler son activité et
 * éviter la déconnexion automatique après 15 minutes d'inactivité (F1).
 */
class HeartbeatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'server_time' => now()->toIso8601String(),
            'user' => [
                'id' => $request->user()->id,
                'role' => $request->user()->role->value,
            ],
        ]);
    }
}
