<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApiCredentialRequest;
use App\Models\ApiCredential;
use App\Services\Ai\ApiCredentialResolver;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Écran admin « Clés API » (F4) : saisie des clés Groq/Anthropic, chiffrées
 * en base (R4). Les clés déjà enregistrées ne sont jamais réaffichées en
 * clair, seulement leur statut (configurée / non configurée).
 */
class ApiCredentialController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ApiCredentialResolver $resolver,
    ) {}

    public function edit(): View
    {
        $providers = [
            'groq' => 'Groq (transcription vocale)',
            'anthropic' => 'Anthropic (raffinage et rédaction IA)',
        ];

        $statuses = collect($providers)->mapWithKeys(fn ($label, $provider) => [
            $provider => $this->resolver->isConfigured($provider),
        ]);

        return view('admin.settings.api-credentials', compact('providers', 'statuses'));
    }

    public function update(ApiCredentialRequest $request): RedirectResponse
    {
        $map = [
            'groq_api_key' => 'groq',
            'anthropic_api_key' => 'anthropic',
        ];

        foreach ($map as $field => $provider) {
            $value = trim((string) $request->input($field, ''));

            if ($value === '') {
                continue;
            }

            ApiCredential::updateOrCreate(
                ['provider' => $provider],
                ['api_key' => $value, 'updated_by' => $request->user()->id]
            );

            $this->audit->log('cle_api_mise_a_jour', $request->user(), request: $request);
        }

        return redirect()
            ->route('admin.settings.api-credentials.edit')
            ->with('status', 'Clés API mises à jour.');
    }
}
