<?php

namespace App\Http\Requests\Officer;

use Illuminate\Foundation\Http\FormRequest;

class VerificationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:verified,reopen'],
            'note'     => ['nullable', 'string', 'max:2000'],
        ];
    }
}
