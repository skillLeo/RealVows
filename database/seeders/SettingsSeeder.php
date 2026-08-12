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
            /*
             * ⚠️ THE TRUSTED-CONTACT REQUIREMENT IS UNSET ON PURPOSE.
             *
             * The client does not want it presented to men and believes many
             * members will not have anybody to name. Compliance wants it. That
             * argument is not settled, so it is a setting rather than a branch
             * and it ships with no answer chosen.
             *
             * `off` is the seeded value, and it is the ABSENCE of a decision
             * rather than a decision: it leaves the product behaving exactly as
             * it did before the system existed. Choosing `required_all` here
             * would resolve a live disagreement quietly, in a seeder, and
             * present it as an implementation detail.
             *
             * The description says so in the words the person choosing needs.
             */
            'guardian' => [
                ['key' => 'guardian_requirement', 'label' => 'Trusted contact requirement', 'type' => 'select', 'options' => [
                    'off' => 'Off — not asked for at all',
                    'optional' => 'Optional — members may add one',
                    'required_female' => 'Required for women',
                    'required_all' => 'Required for everyone',
                ], 'description' => 'Nobody has chosen this yet. It is off, which means the product behaves exactly as it did before the trusted-contact system was built. Changing it takes effect immediately and needs no deploy.'],
            ],

            'general' => [
                /*
                 * ⚠️ A REAL MODE, NOT A GATEWAY LEFT SWITCHED OFF. While this
                 * is on, introductions arrive unlocked, every purchase surface
                 * is HIDDEN rather than disabled, and the copy says so. See
                 * App\Support\FreePeriod — particularly the note on the exit,
                 * which is the direction that touches real members.
                 */
                ['key' => 'free_period', 'label' => 'Free period', 'type' => 'select', 'options' => ['1' => 'On — introductions are free', '0' => 'Off — introductions cost credits'], 'description' => 'While this is on, nothing is charged and no packages are shown. Members who receive introductions during it keep them permanently when it ends.'],
                ['key' => 'site_name', 'label' => 'Site name', 'type' => 'text'],
                ['key' => 'support_email', 'label' => 'Support email', 'type' => 'email', 'description' => 'Shown to members when they need help.'],
                ['key' => 'admin_notification_email', 'label' => 'Admin notification email', 'type' => 'email', 'description' => 'Where new verification alerts are sent.'],
                ['key' => 'maintenance_mode', 'label' => 'Maintenance mode', 'type' => 'toggle', 'description' => 'Shows a holding page to members. Admins keep access.'],
            ],
            'mail' => [
                /*
                 * ⚠️ THREE DRIVERS, AND THE FIRST IS THE ONE IN USE.
                 *
                 * Resend is not "SMTP with different settings" as far as this
                 * screen is concerned: choosing it fills in the host, port and
                 * username automatically, and the only thing anybody types is
                 * the API key. Presenting it as SMTP meant four fields somebody
                 * had to know the right answers for, and three of them have the
                 * same answer for every Resend account in the world.
                 */
                ['key' => 'driver', 'label' => 'Mail driver', 'type' => 'select', 'options' => ['resend' => 'Resend (recommended)', 'smtp' => 'Custom SMTP', 'log' => 'Log only (no sending)'], 'description' => 'Resend fills in the connection for you — you only need the API key. Custom SMTP is for any other provider.'],
                ['key' => 'resend_api_key', 'label' => 'Resend API key', 'type' => 'password', 'is_secret' => true, 'description' => 'From resend.com → API Keys. Starts re_. Only needed when the driver is Resend.'],
                ['key' => 'host', 'label' => 'SMTP host', 'type' => 'text'],
                ['key' => 'port', 'label' => 'SMTP port', 'type' => 'text'],
                ['key' => 'username', 'label' => 'SMTP username', 'type' => 'text'],
                ['key' => 'password', 'label' => 'SMTP password', 'type' => 'password', 'is_secret' => true],
                ['key' => 'encryption', 'label' => 'Encryption', 'type' => 'select', 'options' => ['tls' => 'TLS', 'ssl' => 'SSL', '' => 'None']],
                ['key' => 'from_address', 'label' => 'From address', 'type' => 'email'],
                ['key' => 'from_name', 'label' => 'From name', 'type' => 'text'],

                /*
                 * ⚠️ NOT THE API KEY. The API key authorises us to send; this
                 * proves an incoming delivery event really came from Resend.
                 * Different values, different pages in the dashboard, and
                 * conflating them produces a webhook that rejects everything.
                 *
                 * Until it is set, delivery events are dropped and every email
                 * stays at "accepted" — see docs/EMAIL-DELIVERABILITY.md.
                 */
                ['key' => 'webhook_secret', 'label' => 'Webhook signing secret', 'type' => 'password', 'is_secret' => true, 'description' => 'From Resend → Webhooks → Signing secret. Starts whsec_. This is NOT the API key.'],
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
                ['key' => 'template_name', 'label' => 'Match template name', 'type' => 'text', 'description' => 'Only fill this in once the template is APPROVED, not merely submitted. An unapproved name fails permanently on every send.'],
                ['key' => 'template_language', 'label' => 'Template language', 'type' => 'text', 'description' => 'The language code the template was approved under, e.g. en or en_US. A mismatch here fails the send even when the template name is right.'],

                /*
                 * ⚠️ THESE TWO ARE NEEDED BEFORE ANY OF THE FOUR ABOVE.
                 *
                 * Meta will not issue a phone number ID until the callback URL
                 * has answered a verification challenge, so the verify token is
                 * the first WhatsApp value that has to exist — earlier than the
                 * access token, and earlier than Phase 3.
                 *
                 * ⚠️ SEEDED EMPTY, AND THE VERIFY TOKEN MUST NEVER GAIN A
                 * DEFAULT. A shared secret with a value in the repository is
                 * the same secret on every deployment, is in the git history
                 * for good, and is the single value deciding whether an unknown
                 * caller gets to claim to be Meta. Generate one instead:
                 *
                 *     php artisan whatsapp:verify-token
                 *
                 * Note the descriptor in `payments` DOES carry a default, and
                 * that is not an inconsistency — a descriptor is a statement
                 * about what a member will see, not a credential.
                 */
                ['key' => 'verify_token', 'label' => 'Webhook verify token', 'type' => 'password', 'is_secret' => true, 'description' => 'The shared secret Meta echoes back when verifying the callback URL. Generate with: php artisan whatsapp:verify-token'],
                ['key' => 'app_secret', 'label' => 'Meta app secret', 'type' => 'password', 'is_secret' => true, 'description' => 'Proves an incoming delivery really came from Meta. This is NOT the access token — different value, different page in the Meta dashboard. Until it is set, deliveries are accepted unverified and every log line says so.'],
            ],
            // Consumed in Phase 2.
            'payments' => [
                ['key' => 'gateway', 'label' => 'Gateway', 'type' => 'select', 'options' => ['powertranz' => 'PowerTranz']],
                ['key' => 'merchant_id', 'label' => 'Merchant ID', 'type' => 'text'],
                ['key' => 'terminal_id', 'label' => 'Terminal ID', 'type' => 'text'],
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password', 'is_secret' => true],
                ['key' => 'environment', 'label' => 'Environment', 'type' => 'select', 'options' => ['sandbox' => 'Sandbox', 'live' => 'Live'], 'description' => 'Stays on sandbox until the merchant account clears.'],

                /*
                 * ⚠️ THE BILLING IDENTITY. The legal entity and the trading
                 * name are genuinely different for this product.
                 *
                 * "RealVows" could not be registered as a company in time, so
                 * the gateway account sits under a company the client already
                 * controls, with RealVows as the trading name. Money settles
                 * to one business; the payer sees another name on their card
                 * statement. That gap is the commonest cause of a chargeback,
                 * which is why the receipt states the descriptor outright.
                 *
                 * ⚠️ These ship EMPTY on purpose and stay empty until the
                 * client confirms the entity with his bank. A placeholder
                 * company name on a receipt is a false statement about who is
                 * holding somebody's money.
                 */
                ['key' => 'billing_legal_entity', 'label' => 'Billing — registered company', 'type' => 'text', 'description' => 'The company that actually receives the money. Leave empty until the bank confirms it.'],
                ['key' => 'billing_trading_name', 'label' => 'Billing — trading name', 'type' => 'text', 'description' => 'The name the member knows. Normally RealVows.'],
                ['key' => 'statement_descriptor', 'label' => 'Billing — statement descriptor', 'type' => 'text', 'description' => 'Exactly what appears on a card statement. Uppercase letters, digits and spaces only, 22 characters maximum — that is what the card networks accept. Must match what the gateway is configured to send.'],
                ['key' => 'billing_address', 'label' => 'Billing — registered address', 'type' => 'textarea', 'description' => 'The registered address of the company above.'],
                ['key' => 'billing_support_email', 'label' => 'Billing — billing enquiries', 'type' => 'text', 'description' => 'Where a member writes about a charge they do not recognise.'],
            ],
            /*
             * Prices and credit counts are editable, which is safe because
             * every purchase snapshots both onto its own row — changing a
             * price never rewrites a receipt. The package KEYS are not
             * editable: they are in the database enum and in checkout URLs.
             */
            'packages' => [
                ['key' => 'single_label', 'label' => 'Single — name', 'type' => 'text'],
                ['key' => 'single_credits', 'label' => 'Single — introductions', 'type' => 'text'],
                ['key' => 'single_price_cents', 'label' => 'Single — price in cents', 'type' => 'text', 'description' => 'US cents. 2000 is $20.'],
                ['key' => 'single_badge', 'label' => 'Single — badge', 'type' => 'text', 'description' => 'Leave empty for no badge.'],

                ['key' => 'triple_label', 'label' => 'Triple — name', 'type' => 'text'],
                ['key' => 'triple_credits', 'label' => 'Triple — introductions', 'type' => 'text'],
                ['key' => 'triple_price_cents', 'label' => 'Triple — price in cents', 'type' => 'text'],
                ['key' => 'triple_badge', 'label' => 'Triple — badge', 'type' => 'text'],

                ['key' => 'octet_label', 'label' => 'Octet — name', 'type' => 'text'],
                ['key' => 'octet_credits', 'label' => 'Octet — introductions', 'type' => 'text'],
                ['key' => 'octet_price_cents', 'label' => 'Octet — price in cents', 'type' => 'text'],
                ['key' => 'octet_badge', 'label' => 'Octet — badge', 'type' => 'text'],
            ],

            'matching' => [
                ['key' => 'minimum_score_threshold', 'label' => 'Minimum score', 'type' => 'text', 'description' => 'Below this, two members are never introduced. Between 50 and 85.'],
                ['key' => 'matches_per_batch', 'label' => 'Matches per batch', 'type' => 'text', 'description' => 'How many introductions each verified member is shown. Between 3 and 12.'],
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

            /*
             * ⚠️ The one billing field with a sensible default. The legal
             * entity cannot be guessed — it is whichever company the client's
             * bank confirms — but the descriptor is what the MEMBER should
             * see, and that is RealVows regardless of who settles the money.
             *
             * Already conformant: uppercase, alphanumeric, 22 characters or
             * fewer. A descriptor the card networks reject is a silent
             * failure at underwriting rather than a visible error.
             */
            'payments.statement_descriptor' => 'REALVOWS',
            'whatsapp.provider' => 'meta',

            'packages.single_label' => 'One introduction',
            'packages.single_credits' => '1',
            'packages.single_price_cents' => '2000',
            'packages.single_badge' => '',
            'packages.triple_label' => 'Three introductions',
            'packages.triple_credits' => '3',
            'packages.triple_price_cents' => '5000',
            'packages.triple_badge' => 'Most chosen',
            'packages.octet_label' => 'Eight introductions',
            'packages.octet_credits' => '8',
            'packages.octet_price_cents' => '10000',
            'packages.octet_badge' => 'Best value',
            'guardian.guardian_requirement' => 'off',
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
