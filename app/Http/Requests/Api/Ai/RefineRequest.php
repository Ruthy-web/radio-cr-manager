<?php

namespace App\Http\Requests\Api\Ai;

use Illuminate\Foundation\Http\FormRequest;

class RefineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'results' => ['array'],
            'results.*' => ['string'],
            'conclusion' => ['nullable', 'string'],
            'dictation' => ['required', 'string'],
        ];
    }
}
