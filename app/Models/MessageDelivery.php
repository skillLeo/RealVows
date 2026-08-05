<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to reach one member on one channel.
 *
 * ⚠️ THE STATUSES ARE A ONE-WAY LADDER AND MUST NOT GO BACKWARDS.
 *
 * Meta's callbacks are not ordered. A `delivered` can arrive after a `read`
 * when a phone reconnects and flushes a queue — so applying whatever arrived
 * last would demote a message the member has already opened, and the admin
 * panel would show a message going backwards.
 */
class MessageDelivery extends Model
{
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_EMAIL = 'email';

    public const STATUS_QUEUED = 'queued';

    /**
     * ⚠️ HANDED TO THE MAIL SERVER, AND NOTHING MORE IS KNOWN.
     *
     * The highest status an email can honestly reach on the current transport.
     * Hostinger SMTP returns a 250 and tells us nothing afterwards: a hard
     * bounce to a dead address, a silent drop into spam, and a message read
     * twice are indistinguishable from here — and all three look like success.
     *
     * ⚠️ IT MUST NEVER BE DISPLAYED AS "DELIVERED". See label().
     */
    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_READ = 'read';

    public const STATUS_FAILED = 'failed';

    /**
     * How far along each status is. `failed` sits at the top on purpose: it is
     * terminal, and nothing Meta sends afterwards may quietly un-fail a
     * message that has already triggered an email fallback.
     */
    private const RANK = [
        self::STATUS_QUEUED => 0,
        // Below `sent`: it is a weaker claim, not an equal one.
        self::STATUS_ACCEPTED => 1,
        self::STATUS_SENT => 2,
        self::STATUS_DELIVERED => 3,
        self::STATUS_READ => 4,
        self::STATUS_FAILED => 5,
    ];

    /**
     * ⚠️ THE CEILING EACH CHANNEL CAN HONESTLY REACH.
     *
     * WhatsApp earns its statuses — Meta posts back facts about a real handset.
     * SMTP gives us a 250 and silence, so email stops at `accepted` until the
     * transport can report a bounce. Anything above that would be the admin
     * panel inventing knowledge it does not have.
     *
     * Raising email's ceiling is a deliberate act that belongs with the change
     * that earns it: a sending provider with a delivery webhook, or a polled
     * bounce mailbox. See docs/EMAIL-DELIVERABILITY.md.
     */
    private const CEILING = [
        self::CHANNEL_WHATSAPP => self::STATUS_READ,

        /*
         * ⚠️ RAISED FROM `accepted` TO `delivered` ON 5 AUGUST 2026, WHEN THE
         * RESEND WEBHOOK LANDED AND EARNED IT.
         *
         * It sat at `accepted` because SMTP returns a 250 at handover and
         * reports nothing afterwards — a hard bounce and a message read twice
         * were indistinguishable, and both looked like success.
         *
         * Resend posts back delivered, bounced and complained, so `delivered`
         * is now a fact we are told rather than one we assume.
         *
         * ⚠️ IT STOPS AT `delivered`, NOT `read`. Reading requires a tracking
         * pixel, and this product deliberately sends no images at all — an
         * inbox is a preview pane on a shared laptop and a lock screen at a
         * family dinner. We do not know when an email was opened and we are
         * not going to start knowing.
         */
        self::CHANNEL_EMAIL => self::STATUS_DELIVERED,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'retryable' => 'boolean',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'fell_back_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchRecord::class, 'match_id');
    }

    /**
     * Apply a status from a provider callback, refusing to go backwards.
     *
     * Returns whether anything changed, so a caller can avoid re-triggering
     * work on a repeated callback.
     */
    public function applyStatus(string $status, ?\DateTimeInterface $at = null): bool
    {
        if (! isset(self::RANK[$status])) {
            return false;
        }

        if (self::RANK[$status] <= self::RANK[$this->status]) {
            return false;
        }

        /*
         * ⚠️ A CHANNEL CANNOT CLAIM MORE THAN IT CAN KNOW.
         *
         * `failed` is exempt: a bounce we DO learn about is real information,
         * and refusing it would be the opposite mistake — suppressing a known
         * failure to protect a rule about unknowable successes.
         */
        if ($status !== self::STATUS_FAILED) {
            $ceiling = self::CEILING[$this->channel] ?? self::STATUS_READ;

            if (self::RANK[$status] > self::RANK[$ceiling]) {
                return false;
            }
        }

        $column = match ($status) {
            self::STATUS_ACCEPTED, self::STATUS_SENT => 'sent_at',
            self::STATUS_DELIVERED => 'delivered_at',
            self::STATUS_READ => 'read_at',
            self::STATUS_FAILED => 'failed_at',
            default => null,
        };

        $this->status = $status;

        if ($column) {
            $this->{$column} = $at ?? now();
        }

        $this->save();

        return true;
    }

    /**
     * ⚠️ WHAT AN ADMINISTRATOR IS ALLOWED TO READ OFF THIS ROW.
     *
     * The wording is channel-specific because the same word means different
     * things on the two transports, and the difference is the whole point.
     *
     * On WhatsApp, "Delivered" means Meta told us it reached a handset. On
     * email there is no such fact available, so the label says what actually
     * happened — the mail server took it — rather than borrowing a word that
     * would imply somebody received something.
     *
     * ⚠️ NEVER SHORTEN THE EMAIL LABEL TO "Sent". The whole failure this
     * guards against is a screen that looks green while a message bounced into
     * a void, and "Sent" is exactly the word that would restore it.
     */
    public function label(): string
    {
        if ($this->channel === self::CHANNEL_EMAIL) {
            return match ($this->status) {
                self::STATUS_QUEUED => 'Waiting to send',
                self::STATUS_ACCEPTED => 'Handed to the mail server',
                self::STATUS_FAILED => 'Failed',
                default => ucfirst($this->status),
            };
        }

        return match ($this->status) {
            self::STATUS_QUEUED => 'Waiting to send',
            self::STATUS_SENT => 'Sent to WhatsApp',
            self::STATUS_DELIVERED => 'Delivered to their phone',
            self::STATUS_READ => 'Read',
            self::STATUS_FAILED => 'Failed',
            default => ucfirst($this->status),
        };
    }

    /**
     * ⚠️ THE HONEST CAVEAT, CARRIED WITH THE ROW RATHER THAN LEFT TO A README.
     *
     * Null when the status means what it says. A sentence when the status is
     * the most we can establish and could still be hiding a failure — so the
     * admin panel can print it beside the label and nobody has to remember
     * that email is different.
     */
    public function uncertainty(): ?string
    {
        if ($this->channel !== self::CHANNEL_EMAIL || $this->status !== self::STATUS_ACCEPTED) {
            return null;
        }

        /*
         * ⚠️ NARROWER THAN IT WAS. `accepted` used to be the ceiling and now
         * it is a waypoint: Resend confirms delivery, so a row still sitting
         * at accepted means we are waiting for that confirmation rather than
         * unable to ever get one.
         */
        return 'Handed over and not yet confirmed delivered. Resend usually confirms within '
            .'seconds — a row still here after an hour is worth looking at.';
    }

    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /** A failed WhatsApp message that email has not yet answered. */
    public function needsFallback(): bool
    {
        return $this->hasFailed()
            && $this->channel === self::CHANNEL_WHATSAPP
            && $this->fell_back_at === null;
    }
}
