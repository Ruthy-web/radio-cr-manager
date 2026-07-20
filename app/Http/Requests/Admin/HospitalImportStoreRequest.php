<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HospitalImportStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('hospitals', 'slug')],
            'radiologist_name' => ['nullable', 'string', 'max:255'],
            'colors.primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'exams' => ['nullable', 'array'],
            'exams.*.title' => ['nullable', 'string', 'max:255'],
            'exams.*.requires_side' => ['nullable'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom de l\'hôpital',
            'slug' => 'identifiant (slug)',
            'radiologist_name' => 'nom du radiologue',
            'colors.primary' => 'couleur principale',
        ];
    }
}
