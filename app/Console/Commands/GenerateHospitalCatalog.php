<?php

namespace App\Console\Commands;

use App\Services\HospitalDocxParser;
use Illuminate\Console\Command;

/**
 * Outil de développement : reconstruit database/seeders/data/templates.json
 * à partir des DOCX institutionnels stockés dans storage/app/templates/.
 * Ne s'exécute pas en production ; le catalogue généré est ensuite relu et
 * corrigé à la main avant d'être commité (F2).
 */
class GenerateHospitalCatalog extends Command
{
    protected $signature = 'app:generate-hospital-catalog';

    protected $description = 'Reconstruit database/seeders/data/templates.json à partir des DOCX institutionnels';

    /** @var array<int, array{slug: string, name: string, file: string, radiologist_name: string}> */
    private const HOSPITALS = [
        [
            'slug' => 'nkoulou',
            'name' => 'Clinique NKOULOU — Cabinet Polyclinique de la Cité',
            'file' => 'nkoulou.docx',
            'radiologist_name' => 'Dr E. NDONGO',
        ],
        [
            'slug' => 'hmr1',
            'name' => 'Hôpital Militaire de Région N°1',
            'file' => 'hmr1.docx',
            'radiologist_name' => 'Dr NDONGO / PR ZEH Odile Fernande',
        ],
        [
            'slug' => 'chracerh',
            'name' => 'CHRACERH',
            'file' => 'chracerh.docx',
            'radiologist_name' => 'Dr E. NDONGO',
        ],
        [
            'slug' => 'chm',
            'name' => 'Complexe Hospitalier La MAMU',
            'file' => 'chm.docx',
            'radiologist_name' => 'Dr E. NDONGO',
        ],
        [
            'slug' => 'zalom',
            'name' => 'Hôpital de Référence de Zalom (FALC / Ad Lucem)',
            'file' => 'zalom.docx',
            'radiologist_name' => 'Dr Eric NDONGO',
        ],
    ];

    public function handle(HospitalDocxParser $parser): int
    {
        $catalog = [];

        foreach (self::HOSPITALS as $hospital) {
            $path = storage_path('app/templates/'.$hospital['file']);

            if (! is_file($path)) {
                $this->warn("Fichier introuvable, hôpital ignoré : {$path}");

                continue;
            }

            $result = $parser->parse($path);

            $this->info(sprintf('%s : %d examens détectés', $hospital['name'], count($result['exams'])));

            $catalog[] = [
                'slug' => $hospital['slug'],
                'name' => $hospital['name'],
                'radiologist_name' => $hospital['radiologist_name'],
                'header_docx_path' => 'templates/'.$hospital['file'],
                'colors' => $result['colors'],
                'exams' => $result['exams'],
            ];
        }

        $outputPath = database_path('seeders/data/templates.json');

        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), recursive: true);
        }

        file_put_contents(
            $outputPath,
            json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->info("Catalogue écrit dans {$outputPath}");

        return self::SUCCESS;
    }
}
