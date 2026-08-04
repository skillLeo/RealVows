<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\IntroductionDelivered;
use App\Mail\IntroductionsUnlocked;
use App\Mail\MutualInterest;
use App\Mail\PurchaseReceipt;
use App\Mail\SomeoneInterested;
use App\Mail\TeaserExpiring;
use App\Mail\VerificationApproved;
use App\Mail\VerificationRejected;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Verification;
use App\Services\Settings\RuntimeMailConfigurator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

/**
 * Rendering every email, so they can be checked in real clients.
 *
 * ⚠️ A BROWSER IS NOT A MAIL CLIENT, AND THIS ROUTE MUST NOT BE MISTAKEN FOR
 * THE CHECK.
 *
 * Chrome renders these correctly no matter what is wrong with them. Outlook
 * renders through Word, which ignores most CSS, implements neither flexbox nor
 * grid, and reflows anything positioned; Gmail strips <style> blocks; Apple
 * Mail on iOS rescales fonts on its own initiative.
 *
 * So this route exists in two halves and the second is the real one:
 *
 *   /admin/emails/{key}       — renders in the browser. Fast, catches wrong
 *                               copy and broken Blade. Proves nothing about
 *                               how a client will draw it.
 *   /admin/emails/{key}/send  — sends the actual multipart message to a real
 *                               address, which is the only way to see what
 *                               genuinely arrives.
 *
 * ⚠️ SAMPLE DATA IS REALISTIC ON PURPOSE. Lorem ipsum has no long words, no
 * apostrophes, no accents and no two-line names, so it hides exactly the
 * wrapping and encoding faults a preview exists to find.
 */
class EmailPreviewController extends Controller
{
    /** @return array<string, array{label: string, build: callable}> */
    private function catalogue(): array
    {
        $recipient = $this->sampleRecipient();

        return [
            'introduction-delivered' => [
                'label' => 'Introduction delivered — the flagship',
                'build' => fn () => new IntroductionDelivered(
                    $recipient,
                    count: 1,
                    score: 87,
                    // A real rationale: profile-derived, no affinity vocabulary.
                    rationale: 'You are both settled in Kingston, both want children within '
                        .'a few years, and you each named family and faith as the things that '
                        .'matter most. Neither of you is in a hurry, which is rarer than it sounds.',
                ),
            ],
            'introductions-unlocked' => [
                'label' => 'Introductions unlocked — after payment',
                'build' => fn () => new IntroductionsUnlocked($recipient, count: 3),
            ],
            'mutual-interest' => [
                'label' => 'Mutual interest — the only email naming another member',
                'build' => fn () => new MutualInterest($recipient, otherFirstName: 'Omar'),
            ],
            'someone-interested' => [
                'label' => 'Someone interested — no identifying detail',
                'build' => fn () => new SomeoneInterested($recipient),
            ],
            'teaser-expiring' => [
                'label' => 'Teaser expiring — seven days out',
                'build' => fn () => new TeaserExpiring($recipient, count: 2, daysLeft: 7),
            ],
            'verification-approved' => [
                'label' => 'Verification approved',
                'build' => fn () => new VerificationApproved($this->sampleVerification($recipient)),
            ],
            'verification-rejected' => [
                'label' => 'Verification rejected — carries the reason',
                'build' => fn () => new VerificationRejected(
                    $this->sampleVerification($recipient),
                    'The photograph of your identification was too blurred to read. A second '
                        .'attempt in daylight is usually enough.',
                ),
            ],
            'receipt' => [
                'label' => 'Purchase receipt — confirm it survived the copy deck migration',
                'build' => fn () => new PurchaseReceipt($this->samplePurchase($recipient)),
            ],
        ];
    }

    public function index(): Response
    {
        $rows = '';

        foreach ($this->catalogue() as $key => $entry) {
            $rows .= sprintf(
                '<li style="margin:0 0 12px;"><a href="/admin/emails/%s">%s</a> '
                    .'<span style="color:#6B5F62;font-size:13px;">— <a href="/admin/emails/%s?format=text">plain text</a></span></li>',
                $key, e($entry['label']), $key,
            );
        }

        return response(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Email previews</title>'
            .'<style>body{font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;max-width:640px;margin:48px auto;padding:0 16px;line-height:1.6;color:#1F1518}'
            .'h1{font-family:Georgia,serif;font-weight:400;color:#6B1834}code{background:#F2EFE9;padding:2px 6px;border-radius:4px}</style></head><body>'
            .'<h1>Email previews</h1>'
            .'<p><strong>A browser is not a mail client.</strong> These render correctly in Chrome '
            .'no matter what is wrong with them. Use the send form below to check what actually '
            .'arrives in Gmail, Apple Mail and Outlook.</p>'
            ."<ul style=\"padding-left:20px;\">{$rows}</ul>"
            .'<h2 style="font-family:Georgia,serif;font-weight:400;color:#6B1834;">Send a real one</h2>'
            .'<form method="POST" action="/admin/emails/send">'
            .csrf_field()
            .'<p><select name="key" style="padding:8px;font-size:15px;">'
            .implode('', array_map(
                fn ($k, $e) => '<option value="'.$k.'">'.e($e['label']).'</option>',
                array_keys($this->catalogue()),
                $this->catalogue(),
            ))
            .'</select></p>'
            .'<p><input type="email" name="to" placeholder="you@example.com" required '
            .'style="padding:8px;font-size:15px;width:280px;"></p>'
            .'<p><button type="submit" style="padding:10px 20px;font-size:15px;background:#6B1834;color:#fff;border:0;border-radius:8px;cursor:pointer;">Send it</button></p>'
            .'</form>'
            .'<p style="color:#6B5F62;font-size:14px;">Sends through whatever is configured in '
            .'<code>Settings → Email</code>. If the driver is still <code>log</code>, it goes to '
            .'<code>storage/logs</code> and nothing leaves the server.</p>'
            .'</body></html>',
            200,
            ['Content-Type' => 'text/html'],
        );
    }

    public function show(Request $request, string $key): Response
    {
        $entry = $this->catalogue()[$key] ?? abort(404);

        $mailable = ($entry['build'])();

        /*
         * ⚠️ THE TEXT PART IS PREVIEWABLE SEPARATELY, because it is the part
         * nobody looks at and the part spam filters weigh. Rendered as
         * text/plain so the browser shows it exactly as a client would.
         */
        if ($request->query('format') === 'text') {
            return response(
                $this->renderText($mailable),
                200,
                ['Content-Type' => 'text/plain; charset=utf-8'],
            );
        }

        return response($mailable->render(), 200, ['Content-Type' => 'text/html']);
    }

    /**
     * ⚠️ THE HALF THAT ACTUALLY ANSWERS THE QUESTION.
     *
     * What a client does to a message can only be seen by sending one. This
     * sends the real multipart mailable — both parts, real headers — through
     * whatever SMTP is configured.
     */
    public function send(Request $request, RuntimeMailConfigurator $mail): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string'],
            'to' => ['required', 'email'],
        ]);

        $entry = $this->catalogue()[$validated['key']] ?? abort(404);

        $mail->apply();

        Mail::to($validated['to'])->send(($entry['build'])());

        return back()->with(
            'success',
            'Sent to '.$validated['to'].'. If the mail driver is still "log", it went to '
                .'storage/logs and nothing left the server.'
        );
    }

    private function renderText(object $mailable): string
    {
        // Laravel renders the HTML view by default; the text view has to be
        // asked for explicitly.
        $reflection = new \ReflectionMethod($mailable, 'textView');
        $reflection->setAccessible(true);

        $data = (new \ReflectionMethod($mailable, 'commonData'));
        $data->setAccessible(true);

        return view($reflection->invoke($mailable), $data->invoke($mailable))->render();
    }

    /**
     * ⚠️ NEVER PERSISTED. These are unsaved models built purely to render.
     *
     * A preview route that writes rows would put invented members into the
     * pool, and the demo teardown at launch would have no way to tell them
     * from the seeded thirty.
     */
    private function sampleRecipient(): User
    {
        $user = new User([
            'name' => 'Aisha Mohammed-Bakare',
            'email' => 'aisha@example.com',
            'phone_country_code' => '+1',
            'phone_number' => '8765550199',
        ]);

        $user->id = 1;

        $profile = new Profile(['first_name' => 'Aisha', 'last_name' => 'Mohammed-Bakare']);
        $user->setRelation('profile', $profile);

        return $user;
    }

    private function sampleVerification(User $user): Verification
    {
        $verification = new Verification(['status' => 'approved']);
        $verification->id = 1;
        $verification->setRelation('profile', $user->profile);

        return $verification;
    }

    private function samplePurchase(User $user): Purchase
    {
        $purchase = new Purchase([
            'package' => 'triple',
            'credits' => 3,
            'amount_cents' => 5000,
            'currency' => 'USD',
            'status' => 'completed',
            'order_identifier' => 'RV-PREVIEW-0001',
        ]);

        $purchase->id = 1;
        $purchase->created_at = now();
        $purchase->setRelation('user', $user);

        return $purchase;
    }
}
