<?php

namespace App\Http\Requests\Api\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string'],
            'hospital_id' => ['required', 'exists:hospitals,id'],
            'exam_template_id' => [
                'nullable',
                Rule::exists('exam_templates', 'id')->where('hospital_id', $this->input('hospital_id')),
            ],
            'patient' => ['nullable', 'array'],
            'patient.age' => ['nullable', 'string', 'max:20'],
            'patient.sex' => ['nullable', 'string', 'max:20'],
            'patient.side' => ['nullable', 'string', 'max:20'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*.kind' => ['required_with:attachments', Rule::in(['image', 'pdf', 'text'])],
            'attachments.*.media_type' => ['nullable', 'string'],
            'attachments.*.data' => ['nullable', 'string'],
            'attachments.*.text' => ['nullable', 'string'],
            'attachments.*.name' => ['nullable', 'string'],
        ];
    }
}
