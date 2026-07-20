<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HospitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hospitalId = $this->route('hospital')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('hospitals', 'slug')->ignore($hospitalId),
            ],
            'radiologist_name' => ['nullable', 'string', 'max:255'],
            'colors.primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'slug' => 'identifiant (slug)',
            'radiologist_name' => 'nom du radiologue',
            'colors.primary' => 'couleur principale',
        ];
    }
}
