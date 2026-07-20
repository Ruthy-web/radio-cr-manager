<?php

namespace App\Services\Ai;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Transcription vocale via Groq (Whisper) — F4. Le fichier audio n'est
 * jamais écrit sur disque côté serveur ni conservé après l'appel (R3) : il
 * n'est transmis qu'en flux au fournisseur, depuis l'upload temporaire de
 * la requête.
 */
class GroqSpeechToTextService
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/audio/transcriptions';

    public function __construct(private readonly ApiCredentialResolver $credentials) {}

    public function model(): string
    {
        return config('services.groq.stt_model', 'whisper-large-v3-turbo');
    }

    /**
     * @return array{text: string, provider: string}
     */
    public function transcribe(UploadedFile $audio, string $language = 'fr'): array
    {
        $key = $this->credentials->resolve('groq');

        if (! $key) {
            throw new RuntimeException('Clé API Groq non configurée. Renseignez-la dans « Clés API » (admin).');
        }

        $response = Http::withToken($key)
            ->timeout(60)
            ->attach('file', file_get_contents($audio->getRealPath()), $audio->getClientOriginalName() ?: 'audio.webm')
            ->post(self::ENDPOINT, [
                'model' => $this->model(),
                'language' => $language,
                'response_format' => 'json',
            ]);

        if ($response->failed()) {
            $message = $response->json('error.message');
            $hint = match ($response->status()) {
                401 => ' — clé API invalide.',
                429 => ' — quota/limite atteint, réessayez plus tard.',
                default => '',
            };

            throw new RuntimeException("Groq HTTP {$response->status()}{$hint}".($message ? ' '.$message : ''));
        }

        return [
            'text' => trim((string) $response->json('text', '')),
            'provider' => 'groq',
        ];
    }
}
