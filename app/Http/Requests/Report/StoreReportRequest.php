<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'description'  => ['required', 'string', 'min:20', 'max:2000'],
            'title'        => ['nullable', 'string', 'max:200'],
            'category_id'  => ['nullable', 'exists:categories,id'],
            'category_code'=> ['nullable', 'exists:categories,code'],
            'latitude'     => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude'    => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'address_text' => ['nullable', 'string', 'max:500'],
            'occurred_at'  => ['required', 'date'],
            'attachments'  => ['nullable', 'array', 'max:3'],
            'attachments.*'=> [
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:' . (int) config('solvara.max_attachment_kb', 5120), // default 5MB
            ],
        ];
    }
}
