<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class HospitalImportAnalyzeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'radiologist_name' => ['nullable', 'string', 'max:255'],
            // 20 Mo — largement suffisant pour un DOCX de comptes rendus normaux.
            'template' => ['required', 'file', 'mimes:docx', 'max:20480'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom de l\'hôpital',
            'radiologist_name' => 'nom du radiologue',
            'template' => 'fichier DOCX',
        ];
    }
}
