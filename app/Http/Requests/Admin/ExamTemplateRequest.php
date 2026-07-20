<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $examId = $this->route('exam_template')?->id;
        $hospitalId = $this->route('hospital')?->id ?? $this->route('exam_template')?->hospital_id;

        return [
            'title' => [
                'required', 'string', 'max:255',
                Rule::unique('exam_templates', 'title')
                    ->where('hospital_id', $hospitalId)
                    ->ignore($examId),
            ],
            'heading' => ['required', 'string', 'max:255'],
            'modality' => ['nullable', 'string', 'max:100'],
            'requires_side' => ['boolean'],
            'indication' => ['nullable', 'string'],
            'technique' => ['nullable', 'string'],
            'results_text' => ['nullable', 'string'],
            'conclusion' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'titre',
            'heading' => 'intitulé imprimé',
            'modality' => 'modalité',
            'results_text' => 'résultats',
        ];
    }

    /**
     * Transforme le texte libre (une constatation par ligne) en tableau
     * structuré compatible avec le moteur d'insertion sémantique (F5).
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
