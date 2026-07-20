<?php

namespace App\Http\Requests\Api\Ai;

use Illuminate\Foundation\Http\FormRequest;

class SttRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 25 Mo — limite acceptée par l'API Groq.
            'audio' => ['required', 'file', 'mimetypes:audio/*,video/webm', 'max:25600'],
            'language' => ['nullable', 'string', 'size:2'],
        ];
    }
}
