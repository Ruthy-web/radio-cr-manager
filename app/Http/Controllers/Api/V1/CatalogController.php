<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use Illuminate\Http\JsonResponse;

/**
 * Catalogue hôpitaux + examens (F2) exposé à la PWA pour mise en cache
 * hors ligne (R5) : sans ce catalogue local, impossible de créer un compte
 * rendu sans réseau.
 */
class CatalogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $hospitals = Hospital::query()
            ->where('active', true)
            ->with(['examTemplates' => fn ($q) => $q->where('active', true)->orderBy('title')])
            ->orderBy('name')
            ->get()
            ->map(fn (Hospital $hospital) => [
                'id' => $hospital->id,
                'slug' => $hospital->slug,
                'name' => $hospital->name,
                'colors' => $hospital->colors,
                'radiologist_name' => $hospital->radiologist_name,
                'exam_templates' => $hospital->examTemplates->map(fn ($exam) => [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'heading' => $exam->heading,
                    'modality' => $exam->modality,
                    'requires_side' => $exam->requires_side,
                    'indication' => $exam->indication,
                    'technique' => $exam->technique,
                    'results' => $exam->results,
                    'conclusion' => $exam->conclusion,
                ]),
            ]);

        return response()->json([
            'hospitals' => $hospitals,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
