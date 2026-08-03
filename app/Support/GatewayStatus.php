<?php

namespace App\Support;

use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PowerTranzGateway;

/**
 * Which payment gateway is actually bound, said out loud.
 *
 * ⚠️ THIS EXISTS BECAUSE THE ANSWER USED TO BE UNANSWERABLE FROM THE BROWSER.
 *
 * Real-versus-fake is not a setting anybody chooses. It is INFERRED:
 * PaymentServiceProvider binds FakeGateway when no `api_key` is stored and
 * the environment is local or demo, and PowerTranzGateway otherwise. That
 * inference is correct, and it is also invisible — the settings screen showed
 * a Gateway dropdown reading "PowerTranz" while the container was quietly
 * handing every checkout to a stand-in.
 *
 * A screen that handles money must never leave "which gateway am I on?" to be
 * worked out from what is absent. This project has been caught four times by
 * state that was true but unstated, and the whole point of Rule 5 is that
 * silence and success look identical.
 *
 * ⚠️ IT ASKS THE CONTAINER, IT DOES NOT RE-DERIVE THE RULE.
 *
 * Reimplementing the binding logic here would give a banner that is right
 * until somebody edits the provider, and then confidently wrong — which is
 * worse than no banner. It resolves the real binding and reports what it got.
 */
class GatewayStatus
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function isFake(): bool
    {
        return $this->gateway instanceof FakeGateway;
    }

    public function isLive(): bool
    {
        return ! $this->isFake() && $this->gateway->mode() === 'live';
    }

    /** The bound class, by its short name — the ground truth. */
    public function className(): string
    {
        return class_basename($this->gateway);
    }

    /**
     * What a human should read.
     *
     * ⚠️ The fake names itself as a stand-in in plain words. "FakeGateway"
     * alone would be read as a class name by a developer and as nothing at
     * all by the client.
     */
    public function label(): string
    {
        if ($this->isFake()) {
            return 'Stand-in gateway — no real payments';
        }

        return $this->isLive()
            ? 'PowerTranz — LIVE, real money'
            : 'PowerTranz — sandbox';
    }

    /**
     * ⚠️ THE HOST, BECAUSE A WRONG HOST IS THE OTHER SILENT FAILURE.
     *
     * Sandbox credentials pointed at the live host, or the reverse, fails in
     * a way that looks like a credential problem and wastes a day. Showing
     * what it actually resolved to costs one line.
     */
    public function host(): ?string
    {
        if ($this->isFake()) {
            return null;
        }

        return PowerTranzGateway::HOSTS[$this->gateway->mode()] ?? null;
    }

    /**
     * @return array{label: string, className: string, host: ?string, isFake: bool, isLive: bool}
     */
    public function forDisplay(): array
    {
        return [
            'label' => $this->label(),
            'className' => $this->className(),
            'host' => $this->host(),
            'isFake' => $this->isFake(),
            'isLive' => $this->isLive(),
        ];
    }
}
