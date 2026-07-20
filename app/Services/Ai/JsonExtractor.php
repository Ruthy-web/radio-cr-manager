<?php

namespace App\Services\Ai;

use App\Services\Ai\Exceptions\UnparsableAiResponseException;

/**
 * Portage fidèle de `extractJsonObject()` (frontend-existant/app.js) : les
 * modèles renvoient parfois le JSON entouré de texte/Markdown, voire tronqué
 * en plein milieu si `max_tokens` est atteint. Trois tentatives successives,
 * de la plus stricte à la plus tolérante, avant d'abandonner.
 */
class JsonExtractor
{
    public static function extract(string $text): array
    {
        $cleaned = trim(preg_replace('/```json|```/i', '', $text) ?? $text);

        $decoded = json_decode($cleaned, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $start = mb_strpos($cleaned, '{');
        $end = mb_strrpos($cleaned, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $slice = mb_substr($cleaned, $start, $end - $start + 1);
            $decoded = json_decode($slice, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        if ($start !== false) {
            $candidate = mb_substr($cleaned, $start);

            $openBraces = substr_count($candidate, '{');
            $closeBraces = substr_count($candidate, '}');
            $openBrackets = substr_count($candidate, '[');
            $closeBrackets = substr_count($candidate, ']');
            $quoteCount = preg_match_all('/(?<!\\\\)"/', $candidate);

            if ($quoteCount % 2 === 1) {
                $candidate .= '"';
            }

            $candidate .= str_repeat(']', max(0, $openBrackets - $closeBrackets));
            $candidate .= str_repeat('}', max(0, $openBraces - $closeBraces));

            $decoded = json_decode($candidate, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        throw new UnparsableAiResponseException($cleaned);
    }
}
