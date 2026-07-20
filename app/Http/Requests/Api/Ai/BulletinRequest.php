<?php

namespace App\Http\Requests\Api\Ai;

use Illuminate\Foundation\Http\FormRequest;

class BulletinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bulletin' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:15360'],
        ];
    }
}
