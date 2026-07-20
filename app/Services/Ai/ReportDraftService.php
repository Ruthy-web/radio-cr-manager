<?php

namespace App\Services\Ai;

use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Services\Ai\Exceptions\UnparsableAiResponseException;
use RuntimeException;

/**
 * Génération IA d'un compte rendu complet (F4) — portage fidèle du prompt
 * système et de la boucle de nouvelle tentative sur troncature de
 * `generateAiDraft()` (frontend-existant/app.js). Ne s'appuie que sur les
 * exemples de style de l'hôpital et le contexte patient explicitement
 * fournis (R2 : zéro invention de donnée non fournie).
 */
class ReportDraftService
{
    private const MAX_ATTEMPTS = 3;

    private const MAX_TOKENS = 4000;

    private const SYSTEM_PROMPT =
        'Tu es un RADIOLOGUE SENIOR (niveau agrege) qui redige des comptes rendus en francais pour un confrere au Cameroun. Exigences ABSOLUES : '.
        "(1) COMPLETUDE SYSTEMATIQUE : decris TOUTES les structures anatomiquement pertinentes de l'examen, y compris les structures normales notees explicitement (ex. une echographie cervicale decrit toujours thyroide, glandes salivaires, aires ganglionnaires). ".
        "(2) STYLE DE L'HOPITAL : reprends strictement la terminologie, l'ordre et le ton des exemples fournis ; le template de l'examen selectionne, s'il est fourni, sert de squelette a modifier. ".
        '(3) CLASSIFICATIONS : enonce explicitement dans la conclusion toute classification applicable (BI-RADS, EU-TIRADS, Bosniak, Kellgren-Lawrence, PI-RADS, LI-RADS, NASCET, Meyerding, FIGO...). '.
        "(4) ZERO INVENTION : jamais de nom, d'age, de sexe, de lateralite ou de medecin non fournis ; laisse ces champs vides ou en pointilles. Les mesures non fournies restent en pointilles (......... mm). ".
        "(5) CONCLUSION : hierarchisee, repond a la question clinique, propose une recommandation de suivi ou d'imagerie complementaire quand c'est cliniquement justifie. ".
        '(6) LONGUEUR : reste aussi complet que necessaire mais SANS remplissage inutile — un compte rendu senior est precis, pas verbeux. '.
        'Reponds UNIQUEMENT par un JSON strict valide, sans texte ni Markdown autour : '.
        '{"heading":"","technique":"","results":[],"conclusion":""}. '.
        "results = tableau de lignes d'observations (une structure/idee par ligne, chaine de caracteres simple, jamais d'objet imbrique). heading = titre du compte rendu en MAJUSCULES.";

    public function __construct(private readonly AnthropicClient $client) {}

    /**
     * @param  array{age?: ?string, sex?: ?string, side?: ?string}  $patientContext
     * @param  array<int, array{kind: string, media_type?: ?string, data?: ?string, text?: ?string, name?: ?string}>  $attachments
     * @return array{heading: string, technique: string, results: array<int, string>, conclusion: string, model: string, attempts: int}
     */
    public function draft(
        string $prompt,
        Hospital $hospital,
        ?ExamTemplate $currentTemplate,
        array $patientContext,
        array $attachments = [],
    ): array {
        $model = $this->client->defaultModel();

        $examples = $hospital->examTemplates()
            ->where('active', true)
            ->orderBy('title')
            ->limit(8)
            ->get(['title', 'heading', 'technique', 'results', 'conclusion'])
            ->map(fn (ExamTemplate $exam) => [
                'title' => $exam->title,
                'heading' => $exam->heading,
                'technique' => $exam->technique,
                'results' => $exam->results,
                'conclusion' => $exam->conclusion,
            ])
            ->all();

        $baseUserText = 'Hopital : '.$hospital->name."\n".
            ($currentTemplate ? 'TEMPLATE DE L\'EXAMEN SELECTIONNE (squelette a adapter) : '.json_encode([
                'heading' => $currentTemplate->heading,
                'technique' => $currentTemplate->technique,
                'results' => $currentTemplate->results,
                'conclusion' => $currentTemplate->conclusion,
            ], JSON_UNESCAPED_UNICODE)."\n" : '').
            'Exemples de style de cet hopital (JSON) : '.json_encode($examples, JSON_UNESCAPED_UNICODE)."\n".
            'Contexte patient (a ne pas inventer au-dela) : '.json_encode([
                'age' => $patientContext['age'] ?? null,
                'sex' => $patientContext['sex'] ?? null,
                'side' => $patientContext['side'] ?? null,
            ], JSON_UNESCAPED_UNICODE)."\n".
            'Demande du radiologue : '.$prompt;

        $content = [];
        $textDocs = [];

        foreach ($attachments as $attachment) {
            $kind = $attachment['kind'] ?? null;

            if ($kind === 'pdf' && ! empty($attachment['data'])) {
                $content[] = ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $attachment['data']]];
            } elseif ($kind === 'image' && ! empty($attachment['data'])) {
                $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $attachment['media_type'] ?? 'image/jpeg', 'data' => $attachment['data']]];
            } elseif ($kind === 'text' && ! empty($attachment['text'])) {
                $textDocs[] = '--- Fichier "'.($attachment['name'] ?? 'document').'" ---'."\n".$attachment['text'];
            }
        }

        $content[] = ['type' => 'text', 'text' => implode("\n\n", array_filter([implode("\n\n", $textDocs), $baseUserText]))];

        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $messages = [['role' => 'user', 'content' => $content]];

            if ($attempt > 1 && $lastError instanceof UnparsableAiResponseException) {
                $messages[] = ['role' => 'assistant', 'content' => mb_substr($lastError->rawText, 0, 2000)];
                $messages[] = ['role' => 'user', 'content' => 'Ta reponse precedente etait tronquee ou n\'etait pas un JSON valide. '.
                    'Renvoie UNIQUEMENT le JSON complet {"heading":"","technique":"","results":[],"conclusion":""}, '.
                    'sans aucun texte autour, quitte a etre plus concis dans les champs pour tenir dans la limite de longueur.',
                ];
            }

            try {
                $response = $this->client->sendMessage([
                    'model' => $model,
                    'max_tokens' => self::MAX_TOKENS,
                    'system' => self::SYSTEM_PROMPT,
                    'messages' => $messages,
                ]);

                $text = AnthropicClient::textFrom($response);

                if (($response['stop_reason'] ?? null) === 'max_tokens') {
                    $lastError = new UnparsableAiResponseException($text);

                    if ($attempt < self::MAX_ATTEMPTS) {
                        continue;
                    }
                }

                $parsed = $this->normalizeExam(JsonExtractor::extract($text));

                if ($parsed['results'] === [] && $parsed['conclusion'] === '') {
                    throw new RuntimeException('le JSON reçu ne contient ni résultats ni conclusion exploitables');
                }

                return [...$parsed, 'model' => $model, 'attempts' => $attempt];
            } catch (UnparsableAiResponseException $e) {
                $lastError = $e;

                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new RuntimeException(
                        'Génération IA impossible : réponse incomplète ou mal formée après '.self::MAX_ATTEMPTS.' tentatives. '.
                        'Reformulez plus court ou réessayez.'
                    );
                }
            } catch (RuntimeException $e) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }

                // Erreur réseau/API (pas de JSON à corriger) : on retente l'appel
                // initial sans contexte d'erreur, comme côté client d'origine.
                $lastError = null;
            }
        }

        throw new RuntimeException('Génération IA impossible.');
    }

    /**
     * @return array{heading: string, technique: string, results: array<int, string>, conclusion: string}
     */
    private function normalizeExam(array $parsed): array
    {
        $results = $parsed['results'] ?? null;

        if (is_array($results)) {
            $normalizedResults = array_values(array_filter(array_map(
                fn ($item) => trim((string) $item),
                $results
            ), fn ($item) => $item !== ''));
        } elseif (is_string($results)) {
            $normalizedResults = array_values(array_filter(array_map(
                fn ($line) => trim(preg_replace('/^[-•*]\s*/', '', $line) ?? $line),
                preg_split('/\n+/', $results) ?: []
            ), fn ($item) => $item !== ''));
        } else {
            $normalizedResults = [];
        }

        return [
            'heading' => is_string($parsed['heading'] ?? null) ? trim($parsed['heading']) : '',
            'technique' => is_string($parsed['technique'] ?? null) ? trim($parsed['technique']) : '',
            'conclusion' => is_string($parsed['conclusion'] ?? null) ? trim($parsed['conclusion']) : '',
            'results' => $normalizedResults,
        ];
    }
}
