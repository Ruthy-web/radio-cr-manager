<?php

namespace App\Services\Ai;

use Illuminate\Http\UploadedFile;

/**
 * Lecture par IA vision d'un bulletin de demande d'examen photographié,
 * souvent manuscrit (F4) — portage fidèle du prompt de `readWithClaudeVision()`
 * (frontend-existant/app.js). La clé Anthropic reste côté serveur (R4) : le
 * client envoie l'image/le PDF au backend, jamais directement au fournisseur.
 */
class BulletinVisionService
{
    private const PROMPT = <<<'PROMPT'
        Tu lis la PHOTO d'un bulletin de demande d'examen radiologique, souvent MANUSCRIT (Cameroun).
        Transcris fidelement uniquement ce qui est ecrit a la main par le medecin ; ignore l'entete imprimee du cabinet, les tampons et le fond.
        Objectif : extraire surtout NOM du patient, AGE, SEXE, NUMERO du bulletin. Les etiquettes ("Nom", "Age") sont souvent ABSENTES : deduis intelligemment.
        Indices :
        - Le NOM du patient est le nom propre manuscrit principal, souvent precede de Mr/M./Mme/Mlle et NON suivi de "Dr" (le medecin prescripteur signe en bas).
        - Le SEXE se deduit de la civilite : Mr/M. = M ; Mme/Mlle = F. Sinon "".
        - L'AGE : s'il y a une date de naissance (ex 21.01.1974), renvoie-la dans dob ; sinon renvoie l'age en clair dans age.
        - Le NUMERO du bulletin est souvent en haut, pres d'un N°, d'un tampon ou "OK" (ex 686/DN).
        Renvoie UNIQUEMENT un objet JSON strict, sans texte ni Markdown autour :
        {"lastName":"","firstName":"","age":"","dob":"","sex":"","record":"","doctor":"","exam":"","side":"","rawText":""}
        - N'invente jamais. Champs inconnus = chaine vide. rawText = transcription brute du manuscrit.
        PROMPT;

    public function __construct(private readonly AnthropicClient $client) {}

    /**
     * @return array{lastName: string, firstName: string, age: string, dob: string, sex: string, record: string, doctor: string, exam: string, side: string, rawText: string, model: string}
     */
    public function read(UploadedFile $file): array
    {
        $model = $this->client->defaultModel();
        $base64 = base64_encode(file_get_contents($file->getRealPath()));
        $isPdf = $file->getMimeType() === 'application/pdf';

        $mediaBlock = $isPdf
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $file->getMimeType() ?: 'image/jpeg', 'data' => $base64]];

        $response = $this->client->sendMessage([
            'model' => $model,
            'max_tokens' => 900,
            'messages' => [['role' => 'user', 'content' => [$mediaBlock, ['type' => 'text', 'text' => self::PROMPT]]]],
        ]);

        $parsed = JsonExtractor::extract(AnthropicClient::textFrom($response));

        return [
            'lastName' => (string) ($parsed['lastName'] ?? ''),
            'firstName' => (string) ($parsed['firstName'] ?? ''),
            'age' => (string) ($parsed['age'] ?? ''),
            'dob' => (string) ($parsed['dob'] ?? ''),
            'sex' => (string) ($parsed['sex'] ?? ''),
            'record' => (string) ($parsed['record'] ?? ''),
            'doctor' => (string) ($parsed['doctor'] ?? ''),
            'exam' => (string) ($parsed['exam'] ?? ''),
            'side' => (string) ($parsed['side'] ?? ''),
            'rawText' => (string) ($parsed['rawText'] ?? ''),
            'model' => $model,
        ];
    }
}
