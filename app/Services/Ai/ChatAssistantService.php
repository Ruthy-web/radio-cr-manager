<?php

namespace App\Services\Ai;

/**
 * Assistant de recherche clinique/radiologique (F4) — portage fidèle du
 * prompt système et du parsing de réponse (texte + sources de recherche
 * web) de `callClaude()`/`parseClaudeResponse()`
 * (frontend-existant/app.js). La clé Anthropic reste côté serveur (R4).
 */
class ChatAssistantService
{
    private const SYSTEM_PROMPT =
        'Tu es un assistant expert pour un radiologue exercant au Cameroun (contexte Afrique centrale, plateaux techniques variables). '.
        'Reponds en francais, de facon precise, structuree et concise. Tu sais faire deux choses : '.
        '(1) repondre a des questions cliniques/radiologiques (indications, protocoles, semiologie, diagnostics differentiels, classifications comme BI-RADS/TI-RADS/LI-RADS/Bosniak/PI-RADS, conduites a tenir, recommandations recentes) ; '.
        '(2) rediger des comptes rendus radiologiques professionnels complets (Identification, Technique, Resultats, Conclusion) quand on te le demande, y compris des comptes rendus normaux d\'un examen donne. '.
        'Quand des fichiers sont joints (image de radio/scanner/echo, ou PDF de bulletin/protocole), analyse-les et exploite-les. '.
        'Utilise du Markdown clair (titres, listes, gras). Cite tes sources quand tu utilises la recherche web. '.
        'N\'invente jamais de donnee patient ni de reference ; en cas de doute, dis-le. Rappelle que la validation finale revient au medecin.';

    public function __construct(private readonly AnthropicClient $client) {}

    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @return array{text: string, sources: array<int, array{url: string, title: string}>, model: string}
     */
    public function send(array $messages, bool $useWeb): array
    {
        $model = $this->client->defaultModel();

        $body = [
            'model' => $model,
            'max_tokens' => 2000,
            'system' => self::SYSTEM_PROMPT,
            'messages' => $messages,
        ];

        if ($useWeb) {
            $body['tools'] = [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 5]];
        }

        $response = $this->client->sendMessage($body);

        return [...$this->parseResponse($response), 'model' => $model];
    }

    /**
     * @return array{text: string, sources: array<int, array{url: string, title: string}>}
     */
    private function parseResponse(array $response): array
    {
        $blocks = $response['content'] ?? [];
        $textParts = [];
        $sources = [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'text') {
                $textParts[] = $block['text'] ?? '';

                foreach ($block['citations'] ?? [] as $citation) {
                    if (! empty($citation['url'])) {
                        $sources[$citation['url']] = ['url' => $citation['url'], 'title' => $citation['title'] ?? $citation['url']];
                    }
                }
            } elseif (($block['type'] ?? null) === 'web_search_tool_result') {
                foreach ($block['content'] ?? [] as $result) {
                    if (! empty($result['url'])) {
                        $sources[$result['url']] = ['url' => $result['url'], 'title' => $result['title'] ?? $result['url']];
                    }
                }
            }
        }

        $text = trim(implode("\n", $textParts));

        if ($text === '') {
            $text = ($response['stop_reason'] ?? null) === 'max_tokens'
                ? 'Réponse tronquée (limite de longueur atteinte). Reformulez ou demandez une réponse plus courte.'
                : 'Aucune réponse texte reçue.';
        }

        return ['text' => $text, 'sources' => array_values(array_slice($sources, 0, 8))];
    }
}
