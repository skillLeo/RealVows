<?php

namespace Tests\Feature;

use App\Listeners\RefuseUndeliverableAddresses;
use App\Mail\OperationalAlert;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ⚠️ A HARD BOUNCE COSTS MORE THAN THE MESSAGE IT FAILED TO DELIVER.
 *
 * ── How this was found ────────────────────────────────────────────────────
 *
 * The client opened info@realvows.com for the first time and it held ten
 * "Undelivered Mail Returned to Sender" bounces from two days.
 *
 * The demo members carry `@realvows.test` addresses, and `.test` is reserved by
 * RFC 6761 — it never resolves anywhere, deliberately and permanently. The daily
 * trusted-contact reminder chases five of them, so every morning five messages
 * went out, failed, and returned as bounces.
 *
 * ⚠️ THE REAL COST IS SENDING REPUTATION. A brand-new domain is judged on its
 * first few hundred messages, and a steady trickle of hard bounces is the
 * clearest possible signal to a receiving server that a sender does not check
 * who it is writing to. The domain paying for that later is the one carrying
 * every real introduction.
 *
 * ── ⚠️ WHY THE GUARD IS AN EVENT LISTENER ────────────────────────────────
 *
 * A dozen places in this application send mail and there will be more. A check
 * added at each send site is a check somebody forgets at the thirteenth.
 * `MessageSending` is the single point every message passes through.
 */
class NoMailToUnreachableAddressesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    private function alert(): OperationalAlert
    {
        return new OperationalAlert(
            subjectLine: 'RealVows: test',
            intro: 'A message.',
            sections: ['Something' => ['A line.']],
        );
    }

    /** Messages that actually reached the transport. */
    private function delivered(): Collection
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages();
    }

    public function test_nothing_is_sent_to_a_reserved_domain(): void
    {
        /*
         * ⚠️ NOT Mail::fake(), AND THAT IS THE WHOLE DESIGN OF THIS TEST.
         *
         * A fake intercepts before the transport, so the listener never runs
         * and the assertion would pass against a guard that does nothing. The
         * `array` transport is the real path — it collects what was genuinely
         * handed over for delivery, which is exactly the question here.
         */
        config(['mail.default' => 'array']);
        Mail::mailer('array')->getSymfonyTransport()->flush();

        foreach ([
            'guardian-of-girl1@realvows.test',
            'somebody@example.invalid',
            'nobody@my.localhost',
        ] as $address) {
            Mail::to($address)->send($this->alert());
        }

        $this->assertCount(
            0,
            $this->delivered(),
            'A message was handed to the transport for an address that can never resolve. '
                .'Every one of those returns as a hard bounce and is read by receiving servers '
                .'as carelessness on a domain with no reputation to spare.',
        );
    }

    public function test_the_transport_really_does_collect_a_good_one(): void
    {
        /*
         * ⚠️ THE CONTROL FOR THE MECHANISM ABOVE. An empty `messages()` array
         * is what a working refusal looks like AND what a broken transport
         * looks like. This proves the array transport collects at all, so the
         * emptiness above means refusal rather than a test that measures
         * nothing.
         */
        config(['mail.default' => 'array']);
        Mail::mailer('array')->getSymfonyTransport()->flush();

        Mail::to('aisha@gmail.com')->send($this->alert());

        $this->assertCount(1, $this->delivered());
    }

    /**
     * ⚠️ POSITIVE CONTROL, AND THIS FILE IS WORTHLESS WITHOUT IT.
     *
     * A listener that refused EVERY message would satisfy the assertion above
     * completely, and would silently stop the platform sending anything at all
     * — a far worse fault than the bounces it was written to prevent, and one
     * whose only symptom is members never hearing from us.
     */
    public function test_an_ordinary_address_still_goes_out(): void
    {
        Mail::fake();

        Mail::to('aisha@gmail.com')->send($this->alert());

        Mail::assertSent(OperationalAlert::class);
    }

    public function test_the_domains_refused_are_only_the_ones_that_truly_cannot_resolve(): void
    {
        /*
         * ⚠️ A LIST THAT ALSO CAUGHT TYPOS WOULD BE MAKING A JUDGEMENT ABOUT
         * SOMEBODY'S REAL ADDRESS, and being wrong about that means a member
         * silently never hears from us — this same failure pointed the other
         * way. Only the four suffixes reserved by RFC 6761 and RFC 2606.
         */
        $listener = new RefuseUndeliverableAddresses;

        $refuses = new \ReflectionMethod($listener, 'cannotResolve');
        $refuses->setAccessible(true);

        foreach (['a@b.test', 'a@b.invalid', 'a@b.example', 'a@b.localhost'] as $unreachable) {
            $this->assertTrue($refuses->invoke($listener, $unreachable), "{$unreachable} should be refused.");
        }

        foreach ([
            'aisha@gmail.com',
            'info@realvows.com',
            // Deliberately awkward: a real domain that merely CONTAINS a
            // reserved word. Suffix matching, never substring matching.
            'somebody@testing.com',
            'somebody@example.company',
        ] as $reachable) {
            $this->assertFalse($refuses->invoke($listener, $reachable), "{$reachable} must not be refused.");
        }
    }
}
