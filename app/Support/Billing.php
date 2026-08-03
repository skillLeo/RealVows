<?php

namespace App\Support;

use App\Services\Settings\SettingsService;

/**
 * Who is actually taking the money, and what the payer will see on their
 * statement.
 *
 * ⚠️ THE LEGAL ENTITY AND THE TRADING NAME ARE GENUINELY DIFFERENT HERE, AND
 * THAT IS THE WHOLE REASON THIS CLASS EXISTS.
 *
 * "RealVows" could not be registered as a company in time. The gateway
 * account instead sits under a company the client already controls, with
 * RealVows as the trading name — so the money settles to one business while
 * the payer sees another name on their card statement.
 *
 * That gap is the single commonest cause of a chargeback. Somebody sees a
 * charge from a company they have never heard of, does not connect it to the
 * marriage site they signed up to, and calls their bank. The receipt has to
 * close the gap in plain words, before they get to their statement rather
 * than after.
 *
 * ⚠️ NOTHING HERE MAY BE HARDCODED. This replaced a constant class naming a
 * specific company, which was correct for exactly as long as that company was
 * the one settling — and would have been silently wrong on the day it was
 * not. BillingIdentityTest fails the build if a company name reappears in any
 * billing surface.
 *
 * ⚠️ THE DESCRIPTOR DEFAULTS. THE ENTITY DOES NOT. DO NOT "FIX" THIS.
 *
 * This asymmetry is deliberate and it was reviewed and kept. It looks like an
 * oversight — four empty fields and one filled in — so it will read as
 * something to tidy up. It is not.
 *
 * `statement_descriptor` defaults to REALVOWS because it is not a claim about
 * anybody. It tells a member which string will appear on their bank
 * statement, which is true as soon as the gateway is configured to send it,
 * and it is the single fact that prevents a chargeback.
 *
 * `billing_legal_entity` has no default because naming a company that has not
 * been confirmed is a FALSE STATEMENT ABOUT WHO IS HOLDING SOMEBODY'S MONEY.
 * It also appears in the Terms of Service, where it states who the member is
 * contracting with — so a guessed value there is not a cosmetic placeholder,
 * it is a contract naming the wrong party. While it is empty the receipt and
 * the legal pages say the entity is pending, which is honest and costs
 * nothing.
 *
 * Seeding a default for the entity, or copying the trading name into it, is
 * the specific mistake this note exists to prevent. The value arrives when
 * the client's bank confirms it and not before.
 */
class Billing
{
    /**
     * The settings keys, all in the `payments` group.
     *
     * @var list<string>
     */
    public const KEYS = [
        'billing_legal_entity',
        'billing_trading_name',
        'statement_descriptor',
        'billing_address',
        'billing_support_email',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    /** The registered company that actually receives the money. */
    public function legalEntity(): ?string
    {
        return $this->value('billing_legal_entity');
    }

    /** The name the member knows us by. */
    public function tradingName(): ?string
    {
        return $this->value('billing_trading_name');
    }

    /** Exactly what appears on a card statement. */
    public function statementDescriptor(): ?string
    {
        return $this->value('statement_descriptor');
    }

    public function address(): ?string
    {
        return $this->value('billing_address');
    }

    public function supportEmail(): ?string
    {
        return $this->value('billing_support_email');
    }

    /**
     * True once the client has confirmed the entity and filled these in.
     *
     * ⚠️ Until then the receipt must say nothing about who is billing rather
     * than guess. A receipt naming the wrong company is worse than a receipt
     * naming none.
     */
    public function isConfigured(): bool
    {
        return $this->legalEntity() !== null && $this->statementDescriptor() !== null;
    }

    /**
     * The one sentence that prevents chargebacks, or null while unconfigured.
     *
     * ⚠️ IT NAMES THE DESCRIPTOR, NOT THE COMPANY. The member is not being
     * asked to understand the corporate structure — they are being told which
     * string to look for on their statement, which is the only fact that
     * stops them phoning the bank.
     */
    public function statementLine(): ?string
    {
        $descriptor = $this->statementDescriptor();

        if (! $descriptor) {
            return null;
        }

        return "This charge appears on your statement as {$descriptor}.";
    }

    /**
     * ⚠️ Rendered only when the two names genuinely differ. When the trading
     * name IS the legal entity — which is what happens once RealVows is
     * registered in its own right — "RealVows, trading as RealVows" is noise
     * that makes a receipt look automated.
     */
    public function tradingAsLine(): ?string
    {
        $legal = $this->legalEntity();
        $trading = $this->tradingName();

        if (! $legal || ! $trading || $legal === $trading) {
            return null;
        }

        return "{$legal}, trading as {$trading}";
    }

    /**
     * Everything a billing surface needs, in one array.
     *
     * @return array{
     *     legalEntity: ?string, tradingName: ?string, descriptor: ?string,
     *     address: ?string, supportEmail: ?string, statementLine: ?string,
     *     tradingAsLine: ?string, configured: bool
     * }
     */
    public function forDisplay(): array
    {
        return [
            'legalEntity' => $this->legalEntity(),
            'tradingName' => $this->tradingName(),
            'descriptor' => $this->statementDescriptor(),
            'address' => $this->address(),
            'supportEmail' => $this->supportEmail(),
            'statementLine' => $this->statementLine(),
            'tradingAsLine' => $this->tradingAsLine(),
            'configured' => $this->isConfigured(),
        ];
    }

    /** Empty strings are unset, not set-to-nothing. */
    private function value(string $key): ?string
    {
        $value = trim((string) $this->settings->get('payments', $key, ''));

        return $value === '' ? null : $value;
    }
}
