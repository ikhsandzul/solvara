<?php

namespace App\Http\Requests\Submission;

use Illuminate\Foundation\Http\FormRequest;

class OfficerReviewRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'decision'              => ['required', 'in:approve_new,confirm_duplicate,reject'],
            'duplicate_report_id'   => ['nullable', 'string'], // required when confirm_duplicate
            'note'                  => ['nullable', 'string', 'max:2000'],
        ];
    }
}
