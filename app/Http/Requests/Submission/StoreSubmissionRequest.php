<?php

namespace App\Http\Requests\Submission;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $maxKb = (int) config('solvara.max_attachment_kb', 10240);

        return [
            'description'          => ['required', 'string', 'min:20', 'max:2000'],
            'selected_category_id' => ['nullable', 'exists:categories,id'],
            'category_code'        => ['nullable', 'exists:categories,code'],
            'latitude'             => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude'            => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'address_text'         => ['nullable', 'string', 'max:500'],
            'occurred_at'          => ['required', 'date'],
            'client_captured_at'   => ['nullable', 'date'],
            'gps_accuracy_m'       => ['nullable', 'numeric', 'min:0'],
            'idempotency_key'      => ['nullable', 'string', 'max:64'],
            'attachments'          => ['nullable', 'array', 'min:1', 'max:' . (int) config('solvara.report_max_images', 3)],
            'attachments.*'        => ['image', 'mimes:jpeg,jpg,png,webp', 'max:' . $maxKb],
        ];
    }
}
