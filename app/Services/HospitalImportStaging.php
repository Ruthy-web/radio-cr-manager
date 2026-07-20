<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * État intermédiaire de l'assistant « Ajouter un hôpital » (F2 complet)
 * entre l'analyse du DOCX et sa confirmation : le fichier est mis en
 * attente sous `storage/app/private/hospital-imports/`, le catalogue
 * détecté est mis en cache le temps que l'admin relise/corrige la
 * prévisualisation.
 */
class HospitalImportStaging
{
    private const TTL_MINUTES = 30;

    /**
     * @param  array{exams: array<int, array<string, mixed>>, colors: array<string, string>}  $parsed
     */
    public function stage(string $name, ?string $radiologistName, UploadedFile $template, array $parsed): string
    {
        $token = (string) Str::uuid();
        $docxPath = "hospital-imports/{$token}.docx";

        $template->storeAs('hospital-imports', "{$token}.docx", 'local');

        Cache::put($this->key($token), [
            'name' => $name,
            'radiologist_name' => $radiologistName,
            'colors' => $parsed['colors'],
            'exams' => $parsed['exams'],
            'docx_path' => $docxPath,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    public function get(string $token): ?array
    {
        return Cache::get($this->key($token));
    }

    /**
     * Efface l'état intermédiaire. Le DOCX n'est supprimé que s'il n'a pas
     * déjà été déplacé vers `storage/app/templates/` par la confirmation.
     */
    public function clear(string $token): void
    {
        $staged = $this->get($token);

        if ($staged && Storage::disk('local')->exists($staged['docx_path'])) {
            Storage::disk('local')->delete($staged['docx_path']);
        }

        Cache::forget($this->key($token));
    }

    private function key(string $token): string
    {
        return "hospital_import:{$token}";
    }
}
