<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Champs laissés vides = clé existante conservée (on ne réaffiche jamais
 * une clé déjà enregistrée — R4).
 */
class ApiCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'groq_api_key' => ['nullable', 'string', 'max:500'],
            'anthropic_api_key' => ['nullable', 'string', 'max:500'],
        ];
    }
}
