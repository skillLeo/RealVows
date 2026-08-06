<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'exists:settings,group'],
            'values' => ['required', 'array'],
            'values.*' => ['nullable', 'string', 'max:5000'],

            /*
             * ⚠️ THE CARD NETWORKS REJECT ANYTHING ELSE, AND THEY DO IT
             * QUIETLY.
             *
             * A descriptor with lowercase, punctuation or more than 22
             * characters is not refused at save time by anyone — it fails
             * during acquirer underwriting, days later, as a stalled
             * application nobody connects to this field. Validating it here
             * is the only place the client finds out while he can still fix
             * it.
             */
            'values.statement_descriptor' => ['nullable', 'string', 'max:22', 'regex:/^[A-Z0-9][A-Z0-9 ]*$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'group.exists' => 'That settings group does not exist.',
            'values.statement_descriptor.regex' => 'The statement descriptor must be uppercase '
                .'letters, digits and spaces only. That is all the card networks accept.',
            'values.statement_descriptor.max' => 'The statement descriptor cannot be longer than '
                .'22 characters. Card networks truncate anything longer.',
        ];
    }
}
