<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$userId ? 'nullable' : 'required', Password::defaults()],
            'two_factor_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'email' => 'e-mail',
            'role' => 'rôle',
            'password' => 'mot de passe',
            'two_factor_enabled' => 'authentification à deux facteurs',
        ];
    }
}
