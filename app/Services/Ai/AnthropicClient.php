<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client bas niveau pour l'API Messages d'Anthropic (F4). La clé ne quitte
 * jamais le serveur (R4) : seul ce service la porte, jamais le client PWA.
 */
class AnthropicClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    public function __construct(private readonly ApiCredentialResolver $credentials) {}

    public function defaultModel(): string
    {
        return config('services.anthropic.model', 'claude-sonnet-4-6');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function sendMessage(array $body): array
    {
        $key = $this->credentials->resolve('anthropic');

        if (! $key) {
            throw new RuntimeException('Clé API Anthropic non configurée. Renseignez-la dans « Clés API » (admin).');
        }

        $response = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => self::VERSION,
        ])->timeout(60)->post(self::ENDPOINT, $body);

        if ($response->failed()) {
            $message = $response->json('error.message');
            $hint = match ($response->status()) {
                401 => ' — clé API invalide.',
                429 => ' — quota/limite atteint, réessayez plus tard.',
                400 => ' — requête refusée (modèle indisponible ou format).',
                default => '',
            };

            throw new RuntimeException("Anthropic HTTP {$response->status()}{$hint}".($message ? ' '.$message : ''));
        }

        return $response->json();
    }

    /**
     * Extrait le texte concaténé des blocs de type "text" d'une réponse Messages.
     */
    public static function textFrom(array $response): string
    {
        $blocks = $response['content'] ?? [];

        return collect($blocks)
            ->filter(fn ($block) => ($block['type'] ?? null) === 'text')
            ->pluck('text')
            ->implode("\n");
    }
}
