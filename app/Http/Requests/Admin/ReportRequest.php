<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La secrétaire peut saisir l'identité patient mais jamais le contenu
 * médical (F1 : « pas de validation médicale ») — les règles et les champs
 * pris en compte varient donc selon le rôle de l'utilisateur connecté.
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'hospital_id' => ['required', 'exists:hospitals,id'],
            'exam_template_id' => [
                'nullable',
                Rule::exists('exam_templates', 'id')->where('hospital_id', $this->input('hospital_id')),
            ],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_age' => ['nullable', 'string', 'max:20'],
            'patient_sex' => ['nullable', 'string', 'max:20'],
            'file_number' => ['nullable', 'string', 'max:100'],
            'prescriber' => ['nullable', 'string', 'max:255'],
            'exam_date' => ['nullable', 'date'],
            'side' => ['nullable', Rule::in(['droit', 'gauche'])],
        ];

        if ($this->canEditMedicalContent()) {
            $rules += [
                'heading' => ['nullable', 'string', 'max:255'],
                'indication' => ['nullable', 'string'],
                'technique' => ['nullable', 'string'],
                'results_text' => ['nullable', 'string'],
                'conclusion' => ['nullable', 'string'],
            ];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'hospital_id' => 'hôpital',
            'exam_template_id' => 'examen',
            'patient_name' => 'nom du patient',
            'patient_age' => 'âge',
            'patient_sex' => 'sexe',
            'file_number' => 'numéro de dossier',
            'prescriber' => 'prescripteur',
            'exam_date' => 'date d\'examen',
        ];
    }

    public function canEditMedicalContent(): bool
    {
        return $this->user()->hasRole(UserRole::Admin, UserRole::Radiologue);
    }

    /**
     * Construit le tableau `results` à partir du texte libre saisi (une
     * constatation par ligne). Une réédition manuelle réinitialise le
     * marqueur « heading » (sous-titre d'organe) à false, ce qui est
     * acceptable pour une saisie au clavier (F3).
     */
    public function resultsAsArray(): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $this->input('results_text', ''));

        return collect($lines)
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '')
            ->map(fn (string $line) => ['text' => $line, 'abnormal' => false, 'heading' => false])
            ->values()
            ->all();
    }
}
