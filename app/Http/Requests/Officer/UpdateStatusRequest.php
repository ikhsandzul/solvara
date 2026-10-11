<?php

namespace App\Http\Requests\Officer;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in(array_keys(Report::allowedTransitions())),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
