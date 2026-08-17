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

    /**
     * ⚠️ A SELECT MUST RECEIVE ONE OF ITS OWN OPTIONS.
     *
     * Nothing checked this. `values.*` accepted any string up to 5000
     * characters, so a POST could write any value at all into a dropdown
     * setting — and the interface would then render a select matching nothing,
     * which reads as empty rather than as wrong.
     *
     * It became urgent when "off" and "optional" were removed from the trusted
     * contact requirement. Taking them out of the dropdown is only a rule if
     * the endpoint refuses them too: otherwise one crafted request, or one
     * stale script, silently disables every gate on the platform, leaves
     * photographs with nobody to review them, and raises no error anywhere.
     *
     * Read from the setting's own stored options rather than a list here, so
     * this cannot drift from what the seeder defines.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $group = $this->input('group');

            foreach ((array) $this->input('values', []) as $key => $value) {
                $setting = \App\Models\Setting::where('group', $group)->where('key', $key)->first();

                if (! $setting || $setting->type !== 'select' || $value === null || $value === '') {
                    continue;
                }

                $allowed = array_keys($setting->options ?? []);

                if ($allowed !== [] && ! in_array($value, $allowed, true)) {
                    $validator->errors()->add(
                        "values.{$key}",
                        'Choose one of the options shown.'
                    );
                }
            }
        });

        /*
         * ⚠️ THE ANTI-DRIFT RULES ARE ENFORCED AGAINST THE REPOSITORY, AND A
         * SETTINGS ROW IS NOT IN THE REPOSITORY.
         *
         * Every guard that keeps urgency and scarcity out of this product reads
         * files. None of them can see this table. So an admin typing "Only 3
         * left!" into a package badge reaches every member on the pricing page
         * without a commit, without CI, and without anybody reviewing a line —
         * which makes this text box the one hole in the whole guarantee.
         *
         * ⚠️ ONLY THE FIELDS A MEMBER ACTUALLY READS. Running this over an SMTP
         * host or a terminal id would produce baffling errors on fields that
         * cannot carry a sentence. See MemberFacingSettings.
         */
        $validator->after(function ($validator) {
            $group = (string) $this->input('group');

            foreach ((array) $this->input('values', []) as $key => $value) {
                if (! \App\Support\MemberFacingSettings::covers($group, (string) $key)) {
                    continue;
                }

                $rule = new \App\Rules\NoPressureLanguage(
                    \App\Support\MemberFacingSettings::label($group, (string) $key)
                );

                $rule->validate("values.{$key}", $value, function (string $message) use ($validator, $key) {
                    $validator->errors()->add("values.{$key}", $message);
                });
            }
        });
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
