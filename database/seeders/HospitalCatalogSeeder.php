<?php

namespace Database\Seeders;

use App\Models\ExamTemplate;
use App\Models\Hospital;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seede les hôpitaux et leur catalogue d'examens à partir de
 * database/seeders/data/templates.json (F2), généré depuis les DOCX
 * institutionnels réels via `php artisan app:generate-hospital-catalog`.
 *
 * Ces 5 hôpitaux ne sont qu'un jeu de départ : l'architecture (assistant
 * « Ajouter un hôpital », étape 8) permet d'en ajouter un nombre illimité
 * sans aucun cas particulier codé en dur.
 */
class HospitalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/templates.json');

        if (! is_file($path)) {
            throw new RuntimeException(
                "Catalogue introuvable : {$path}. Générez-le avec `php artisan app:generate-hospital-catalog`."
            );
        }

        $catalog = json_decode(file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);

        foreach ($catalog as $hospitalData) {
            $hospital = Hospital::query()->updateOrCreate(
                ['slug' => $hospitalData['slug']],
                [
                    'name' => $hospitalData['name'],
                    'colors' => $hospitalData['colors'],
                    'header_docx_path' => $hospitalData['header_docx_path'],
                    'radiologist_name' => $hospitalData['radiologist_name'],
                    'active' => true,
                ]
            );

            foreach ($hospitalData['exams'] as $exam) {
                ExamTemplate::query()->updateOrCreate(
                    ['hospital_id' => $hospital->id, 'title' => $exam['title']],
                    [
                        'heading' => $exam['heading'],
                        'modality' => $exam['modality'],
                        'requires_side' => $exam['requires_side'],
                        'indication' => $exam['indication'],
                        'technique' => $exam['technique'],
                        'results' => $exam['results'],
                        'conclusion' => $exam['conclusion'],
                        'active' => true,
                    ]
                );
            }
        }
    }
}
