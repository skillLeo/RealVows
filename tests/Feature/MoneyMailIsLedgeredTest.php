<?php

namespace Tests\Feature;

use App\Models\MessageDelivery;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\ScannedFiles;
use Tests\TestCase;

/**
 * ⚠️ A MAILABLE THAT TOUCHES MONEY AND WRITES NO LEDGER ROW IS INVISIBLE TO
 * THE ENTIRE DELIVERY SYSTEM.
 *
 * `SendReceiptEmail` called `Mail::to(...)->send(...)` directly. It worked —
 * receipts went out — and it left no `message_deliveries` row, which meant:
 *
 *   - Resend's webhook had nothing to attach a `delivered` event to, so the
 *     receipt could never be confirmed as arriving.
 *   - It never appeared in the delivery log, the admin panel, or the daily
 *     digest.
 *   - A member whose card was charged and whose receipt silently bounced would
 *     have shown up nowhere at all.
 *
 * That is a hole in the fallback chain at the exact point it matters most: the
 * one message a member reaches for when they do not recognise a charge.
 *
 * ⚠️ THE FAILURE WAS INVISIBLE BECAUSE THE SEND SUCCEEDED. Nothing threw,
 * nothing logged, and every test passed — the same shape as the rest of this
 * project's near-misses, with an unrecorded charge as the consequence.
 */
class MoneyMailIsLedgeredTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Jobs that send a member something about money.
     *
     * ⚠️ NAMED EXPLICITLY RATHER THAN INFERRED. A heuristic over class names
     * would miss the next one for being called something else, and this list
     * failing to compile when a job is renamed is the point.
     */
    private const MONEY_MAIL = [
        \App\Jobs\SendReceiptEmail::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    /**
     * ⚠️ THE BEHAVIOURAL CHECK. A receipt produces a ledger row, full stop.
     */
    public function test_a_receipt_writes_a_delivery_row(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);

        $purchase = Purchase::create([
            'user_id' => $user->id,
            'package_key' => 'single',
            'credits_granted' => 1,
            'amount_cents' => 2000,
            'currency' => 'USD',
            'status' => 'completed',
            'order_identifier' => 'RV-LEDGER-0001',
        ]);

        $this->assertSame(0, MessageDelivery::count());

        (new \App\Jobs\SendReceiptEmail($purchase->id))->handle(
            app(\App\Services\Settings\RuntimeMailConfigurator::class),
            app(\App\Services\Mail\ResendMailer::class),
        );

        $row = MessageDelivery::where('template', 'receipt')->first();

        $this->assertNotNull(
            $row,
            'A receipt was sent and no ledger row exists. Resend\'s webhook has nothing to attach '
                .'a delivered event to, so this receipt is invisible to the delivery log, the '
                .'admin panel and the digest — including if it silently bounces.'
        );

        $this->assertSame($user->id, $row->user_id);
        $this->assertSame(MessageDelivery::CHANNEL_EMAIL, $row->channel);
    }

    /**
     * ⚠️ THE STRUCTURAL CHECK, so the next money-touching mailable cannot
     * bypass the ledger the same way.
     *
     * `Mail::to(...)->send(...)` inside one of these jobs is the exact call
     * that caused this. It is allowed inside ResendMailer, which is the
     * fallback path and writes the row itself.
     */
    public function test_no_money_mail_job_calls_the_mail_facade_directly(): void
    {
        $offenders = [];

        foreach (self::MONEY_MAIL as $job) {
            $path = (new \ReflectionClass($job))->getFileName();
            $source = File::get($path);

            if (preg_match('/Mail::to\s*\(/', $source)) {
                $offenders[] = str_replace(base_path().'/', '', $path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These jobs send mail directly, bypassing the delivery ledger:\n  "
                .implode("\n  ", $offenders)
                ."\n\nSend through App\\Services\\Mail\\ResendMailer, which records the row and "
                .'captures the provider id the delivery webhook needs.'
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). The scan above is a negative assertion over
     * source text — worthless unless the reader can be shown a real offence and
     * a clean call side by side.
     */
    public function test_the_bypass_scan_can_see_a_direct_send(): void
    {
        $reader = fn (string $s) => preg_match('/Mail::to\s*\(/', $s) === 1;

        $this->assertTrue($reader('Mail::to($user->email)->send(new PurchaseReceipt($p));'));
        $this->assertTrue($reader('Mail::to ($user->email)->send($m);'));
        $this->assertFalse($reader('$mailer->send($user, new PurchaseReceipt($p), "receipt");'));
    }

    /**
     * ⚠️ AND THE LIST ITSELF MUST NOT GO STALE.
     *
     * A named list is only as good as its maintenance. If a new job in
     * app/Jobs sends a mailable whose name suggests money, it belongs on the
     * list — and this fails until somebody decides either way.
     */
    public function test_no_money_shaped_job_is_missing_from_the_list(): void
    {
        $known = array_map(fn (string $c) => class_basename($c), self::MONEY_MAIL);

        $suspicious = [];

        foreach (ScannedFiles::withExtension(File::allFiles(app_path('Jobs')), ['php'], 'jobs', 3) as $path) {
            $name = pathinfo($path, PATHINFO_FILENAME);

            if (in_array($name, $known, true)) {
                continue;
            }

            $source = File::get($path);

            // Anything that mails AND mentions money.
            $mails = str_contains($source, 'Mail::') || str_contains($source, 'Mailer');
            $money = preg_match('/Receipt|Purchase|Refund|Invoice|Charge|Credit/i', $source) === 1;

            if ($mails && $money) {
                $suspicious[] = $name;
            }
        }

        $this->assertSame(
            [],
            $suspicious,
            "These jobs send mail and mention money but are not on MONEY_MAIL:\n  "
                .implode("\n  ", $suspicious)
                ."\n\nEither add them, or satisfy yourself they do not need a ledger row — but "
                .'decide, rather than leaving the list quietly incomplete.'
        );
    }
}
