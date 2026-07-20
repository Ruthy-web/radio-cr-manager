<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'code de vérification',
        ];
    }
}
