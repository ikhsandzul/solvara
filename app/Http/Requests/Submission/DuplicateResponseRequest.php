<?php

namespace App\Http\Requests\Submission;

use Illuminate\Foundation\Http\FormRequest;

class DuplicateResponseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'response'              => ['required', 'in:same_issue,different_issue'],
            'candidate_report_id'   => ['nullable', 'string'], // required when same_issue
            'note'                  => ['nullable', 'string', 'max:1000'],
        ];
    }
}
