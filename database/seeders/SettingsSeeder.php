<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Every third-party credential the client will ever need to change,
     * defined once. The fields exist from Phase 1 even where the feature
     * that consumes them arrives later — so when WhatsApp lands in Phase 3
     * he pastes a token into a page he already knows.
     *
     * ⚠️ The matching weights (40/20/15/10/10/5) are deliberately NOT here.
     * They are contractually fixed and live as constants in code, where a
     * stray click cannot change the product.
     */
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            $setting = Setting::firstOrNew([
                'group' => $definition['group'],
                'key' => $definition['key'],
            ]);

            $isNew = ! $setting->exists;

            // Labels, types and ordering are always resynced — they are ours
            // to change. `value` is written ONLY when the row is created, so
            // re-running this seeder on production can never overwrite a
            // credential the client has since set.
            $setting->fill(collect($definition)->except(['group', 'key', 'value'])->all());

            if ($isNew && array_key_exists('value', $definition)) {
                $setting->setPlainValue($definition['value']);
            }

            $setting->save();
        }

        app(\App\Services\Settings\SettingsService::class)->flush();
    }

    private function definitions(): array
    {
        $rows = [
            'general' => [
                ['key' => 'site_name', 'label' => 'Site name', 'type' => 'text'],
                ['key' => 'support_email', 'label' => 'Support email', 'type' => 'email', 'description' => 'Shown to members when they need help.'],
                ['key' => 'admin_notification_email', 'label' => 'Admin notification email', 'type' => 'email', 'description' => 'Where new verification alerts are sent.'],
                ['key' => 'maintenance_mode', 'label' => 'Maintenance mode', 'type' => 'toggle', 'description' => 'Shows a holding page to members. Admins keep access.'],
            ],
            'mail' => [
                ['key' => 'driver', 'label' => 'Mail driver', 'type' => 'select', 'options' => ['smtp' => 'SMTP', 'log' => 'Log only (no sending)']],
                ['key' => 'host', 'label' => 'SMTP host', 'type' => 'text'],
                ['key' => 'port', 'label' => 'SMTP port', 'type' => 'text'],
                ['key' => 'username', 'label' => 'SMTP username', 'type' => 'text'],
                ['key' => 'password', 'label' => 'SMTP password', 'type' => 'password', 'is_secret' => true],
                ['key' => 'encryption', 'label' => 'Encryption', 'type' => 'select', 'options' => ['tls' => 'TLS', 'ssl' => 'SSL', '' => 'None']],
                ['key' => 'from_address', 'label' => 'From address', 'type' => 'email'],
                ['key' => 'from_name', 'label' => 'From name', 'type' => 'text'],
            ],
            'storage' => [
                ['key' => 'driver', 'label' => 'Storage driver', 'type' => 'select', 'options' => ['local' => 'This server', 's3' => 'Amazon S3', 'r2' => 'Cloudflare R2']],
                ['key' => 'key', 'label' => 'Access key', 'type' => 'password', 'is_secret' => true],
                ['key' => 'secret', 'label' => 'Secret key', 'type' => 'password', 'is_secret' => true],
                ['key' => 'region', 'label' => 'Region', 'type' => 'text'],
                ['key' => 'bucket', 'label' => 'Bucket', 'type' => 'text'],
                ['key' => 'endpoint', 'label' => 'Endpoint', 'type' => 'url'],
                ['key' => 'url', 'label' => 'Public URL', 'type' => 'url'],
            ],
            // Consumed in Phase 3.
            'whatsapp' => [
                ['key' => 'provider', 'label' => 'Provider', 'type' => 'select', 'options' => ['meta' => 'Meta Cloud API', 'twilio' => 'Twilio']],
                ['key' => 'phone_number_id', 'label' => 'Phone number ID', 'type' => 'text'],
                ['key' => 'business_account_id', 'label' => 'Business account ID', 'type' => 'text'],
                ['key' => 'access_token', 'label' => 'Access token', 'type' => 'password', 'is_secret' => true],
                ['key' => 'template_name', 'label' => 'Match template name', 'type' => 'text'],
            ],
            // Consumed in Phase 2.
            'payments' => [
                ['key' => 'gateway', 'label' => 'Gateway', 'type' => 'select', 'options' => ['powertranz' => 'PowerTranz']],
                ['key' => 'merchant_id', 'label' => 'Merchant ID', 'type' => 'text'],
                ['key' => 'terminal_id', 'label' => 'Terminal ID', 'type' => 'text'],
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password', 'is_secret' => true],
                ['key' => 'environment', 'label' => 'Environment', 'type' => 'select', 'options' => ['sandbox' => 'Sandbox', 'live' => 'Live'], 'description' => 'Stays on sandbox until the merchant account clears.'],
            ],
            'matching' => [
                ['key' => 'minimum_score_threshold', 'label' => 'Minimum score', 'type' => 'text', 'description' => 'Below this, two members are never introduced.'],
                ['key' => 'matches_per_batch', 'label' => 'Matches per batch', 'type' => 'text'],
            ],
            'verification' => [
                ['key' => 'auto_approve', 'label' => 'Approve automatically', 'type' => 'toggle', 'description' => 'Off. Every profile is checked by a person — that is the promise.'],
                ['key' => 'code_length', 'label' => 'Verification code length', 'type' => 'text'],
                ['key' => 'rejection_reasons', 'label' => 'Rejection reasons', 'type' => 'textarea', 'description' => 'One reason per line. These appear to the reviewer.'],
            ],
        ];

        $defaults = [
            'general.site_name' => 'RealVows',
            'matching.minimum_score_threshold' => '65',
            'matching.matches_per_batch' => '8',
            'verification.auto_approve' => '0',
            'verification.code_length' => '6',
            'verification.rejection_reasons' => implode("\n", [
                'The code in the photo does not match the one we issued',
                'The note is not readable',
                'The face is not clearly visible',
                'This looks like a photo of another photo',
                'The photos do not appear to show the same person',
            ]),
            'mail.driver' => 'log',
            'storage.driver' => 'local',
            'payments.gateway' => 'powertranz',
            'payments.environment' => 'sandbox',
            'whatsapp.provider' => 'meta',
        ];

        $flat = [];
        $order = 0;

        foreach ($rows as $group => $definitions) {
            foreach ($definitions as $definition) {
                $definition['group'] = $group;
                $definition['sort_order'] = $order++;
                $definition['is_secret'] ??= false;
                $definition['description'] ??= null;
                $definition['options'] ??= null;

                $seed = $defaults["{$group}.{$definition['key']}"] ?? null;
                if ($seed !== null) {
                    $definition['value'] = $seed;
                }

                $flat[] = $definition;
            }
        }

        return $flat;
    }
}
