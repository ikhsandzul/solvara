<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type'    => ['required', 'in:satisfied,unsatisfied,other'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
