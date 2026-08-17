<?php

namespace App\Support;

/**
 * ⚠️ THE SETTINGS AN ADMIN CAN TYPE INTO THAT A MEMBER THEN READS.
 *
 * Most settings are plumbing: an SMTP host, a phone number id, a terminal id.
 * Nobody outside the admin panel ever sees them and nothing in them can shape
 * how a member feels about the product.
 *
 * These are different. Every one of them is rendered to a member, which means
 * every one of them is a route around the anti-drift rules that are otherwise
 * enforced against the repository. A test reading `resources/` cannot see a
 * settings row: somebody can type "Only 3 left!" into a package badge and reach
 * every member on the pricing page without a commit, without CI, and without
 * anybody reviewing a line.
 *
 * ⚠️ NUMERIC AND CREDENTIAL FIELDS ARE DELIBERATELY ABSENT. A price, a credit
 * count and a merchant id cannot carry a sentence, and running a language rule
 * over them would only produce confusing errors on fields that cannot offend.
 */
class MemberFacingSettings
{
    /**
     * `group.key`, and the human name used in the error a colleague reads.
     *
     * @var array<string, string>
     */
    public const FIELDS = [
        // The pricing page.
        'packages.single_label' => 'That package name',
        'packages.single_badge' => 'That package badge',
        'packages.triple_label' => 'That package name',
        'packages.triple_badge' => 'That package badge',
        'packages.octet_label' => 'That package name',
        'packages.octet_badge' => 'That package badge',

        // Everywhere, including the browser tab and every email.
        'general.site_name' => 'The site name',
        'mail.from_name' => 'The sender name',

        /*
         * ⚠️ READ BY SOMEBODY WHO HAS JUST BEEN REFUSED. This is the managed
         * list a reviewer picks from when rejecting a verification, so it is
         * shown verbatim to a member at the worst moment they will have here.
         */
        'verification.rejection_reasons' => 'That rejection reason',

        // Receipts.
        'payments.billing_trading_name' => 'The trading name',
        'payments.billing_address' => 'The billing address',
        'payments.billing_support_email' => 'The billing support address',
        'payments.statement_descriptor' => 'The statement descriptor',
    ];

    public static function covers(string $group, string $key): bool
    {
        return array_key_exists("{$group}.{$key}", self::FIELDS);
    }

    public static function label(string $group, string $key): string
    {
        return self::FIELDS["{$group}.{$key}"] ?? 'This';
    }
}
