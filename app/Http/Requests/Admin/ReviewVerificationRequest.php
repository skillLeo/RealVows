<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('verifications.review') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject'],

            // Required only when rejecting: a member is owed a reason they
            // can act on, never a bare refusal.
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required_if' => 'Choose a reason so the member knows what to change.',
        ];
    }
}
