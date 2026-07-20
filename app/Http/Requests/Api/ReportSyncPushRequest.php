<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportSyncPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reports' => ['required', 'array', 'max:50'],
            'reports.*.client_uuid' => ['required', 'uuid'],
            'reports.*.hospital_id' => ['required', 'exists:hospitals,id'],
            'reports.*.exam_template_id' => ['nullable', 'exists:exam_templates,id'],
            'reports.*.patient_name' => ['nullable', 'string', 'max:255'],
            'reports.*.patient_age' => ['nullable', 'string', 'max:20'],
            'reports.*.patient_sex' => ['nullable', 'string', 'max:20'],
            'reports.*.file_number' => ['nullable', 'string', 'max:100'],
            'reports.*.prescriber' => ['nullable', 'string', 'max:255'],
            'reports.*.exam_date' => ['nullable', 'date'],
            'reports.*.content' => ['required', 'array'],
            'reports.*.status' => ['nullable', Rule::in(['brouillon', 'finalise', 'signe'])],
            'reports.*.updated_at' => ['nullable', 'date'],
        ];
    }
}
