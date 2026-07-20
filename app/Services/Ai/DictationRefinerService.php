<?php

namespace App\Services\Ai;

/**
 * Raffinage IA d'une dictée (F4) — portage fidèle du prompt système de
 * `insertDictationWithClaude()` (frontend-existant/app.js) : intègre une
 * dictée de radiologue dans les résultats existants (remplace la ligne de
 * la structure anatomique concernée, ou l'ajoute si absente) et met à jour
 * la conclusion si l'anomalie dictée l'impose. N'invente jamais de donnée
 * non dictée.
 */
class DictationRefinerService
{
    private const SYSTEM_PROMPT =
        "Tu es un radiologue senior. On te donne les RESULTATS actuels d'un compte rendu (liste), la CONCLUSION actuelle, ".
        'et la DICTEE d\'un radiologue décrivant des constatations. Ta tâche : intégrer les constatations dictées dans les résultats. '.
        'REGLE CLE : si une structure anatomique citée dans la dictée (foie, rate, reins, vésicule, pancréas, aorte, utérus, prostate, os, tendon, etc.) '.
        'figure déjà dans une ligne des résultats, REMPLACE cette ligne par la version dictée reformulée proprement (terminologie médicale exacte, style CR). '.
        "Sinon, AJOUTE une nouvelle ligne au bon endroit. Ne touche pas aux lignes non concernées. Corrige la conclusion si la dictée l'impose (ex : passage de 'normal' à une anomalie). ".
        'N\'invente aucune donnée non dictée. Réponds UNIQUEMENT en JSON strict : {"results":["..."],"conclusion":"..."}.';

    public function __construct(private readonly AnthropicClient $client) {}

    /**
     * @param  array<int, string>  $currentResults
     * @return array{results: array<int, string>, conclusion: string, model: string}
     */
    public function refine(array $currentResults, ?string $currentConclusion, string $dictation): array
    {
        $model = $this->client->defaultModel();

        $userMessage = 'RESULTATS actuels : '.json_encode(array_values($currentResults), JSON_UNESCAPED_UNICODE)."\n".
            'CONCLUSION actuelle : '.json_encode($currentConclusion ?? '', JSON_UNESCAPED_UNICODE)."\n".
            'DICTEE : '.json_encode($dictation, JSON_UNESCAPED_UNICODE);

        $response = $this->client->sendMessage([
            'model' => $model,
            'max_tokens' => 1500,
            'system' => self::SYSTEM_PROMPT,
            'messages' => [['role' => 'user', 'content' => $userMessage]],
        ]);

        $parsed = JsonExtractor::extract(AnthropicClient::textFrom($response));

        return [
            'results' => is_array($parsed['results'] ?? null)
                ? array_values(array_map('strval', $parsed['results']))
                : [],
            'conclusion' => is_string($parsed['conclusion'] ?? null) ? $parsed['conclusion'] : ($currentConclusion ?? ''),
            'model' => $model,
        ];
    }
}
