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
use Inertia\Inertia;
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
            /*
             * ⚠️ THE CASE THAT BREAKS OUTLOOK'S TABLE HANDLING.
             *
             * Word decides a table's width from its content and will happily
             * push a 560px email out to 900 if one cell refuses to wrap. A long
             * rationale with no early line break is the realistic way that
             * happens here, and it is invisible in every other client.
             */
            'introduction-delivered-long' => [
                'label' => 'Introduction delivered — long rationale (Outlook table-width case)',
                'build' => fn () => new IntroductionDelivered(
                    $recipient,
                    count: 4,
                    score: 92,
                    rationale: 'You are both settled in Kingston and neither of you is planning to '
                        .'leave, which matters more than it sounds when two people are deciding '
                        .'whether to build something permanent. You each said you want children '
                        .'within a few years rather than immediately, you both named family and '
                        .'faith as the things that matter most to you, and you both described '
                        .'wanting to talk a disagreement through straight away rather than let it '
                        .'sit. Neither of you is in a hurry, and that is rarer than it sounds — '
                        .'most people arrive here wanting something to have happened already.',
                ),
            ],

            /*
             * ⚠️ NO SURNAME, WHICH IS NOW THE NORMAL CASE.
             *
             * The presenter withholds the last name until mutual interest, so
             * most emails go to somebody the template knows only a first name
             * for — and a greeting that renders "Hello ," or a footer with a
             * gap in it would be a permanent, visible fault in the commonest
             * path rather than an edge case.
             */
            'introduction-delivered-no-surname' => [
                'label' => 'Introduction delivered — member with no surname (empty-slot layout)',
                'build' => fn () => new IntroductionDelivered(
                    $this->sampleRecipientWithoutSurname(),
                    count: 1,
                    score: 74,
                    rationale: 'You both put honesty first, and you are looking for the same thing '
                        .'on the same timescale.',
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

    /**
     * ⚠️ THE INDEX IS AN INERTIA PAGE. THE PREVIEWS THEMSELVES ARE NOT, AND
     * THAT SPLIT IS DELIBERATE.
     *
     * This method used to return a hand-built HTML string with inline styles
     * and no layout, so it rendered as a bare white page in the middle of the
     * admin area. It looked broken because it sat outside everything.
     *
     * The reason it was raw is sound and applies to `show`, not here: a preview
     * must render in a document carrying NONE of the product's CSS, or the
     * app's stylesheet leaks in and the preview flatters the email — you are
     * shown a layout Gmail will never produce. `show` therefore stays a bare
     * document, and the links on this page open in a new tab to leave the
     * Inertia app cleanly.
     *
     * This page previews nothing. It is a list and a form, so there is nothing
     * for the shell to contaminate.
     */
    /*
     * ⚠️ \Inertia\Response, fully qualified. `Response` in this file is
     * Illuminate\Http\Response, which `show` still returns because a preview is
     * a bare document. The two live side by side on purpose; naming this one
     * explicitly is what keeps that legible.
     */
    public function index(): \Inertia\Response
    {
        return Inertia::render('Admin/Emails', [
            'emails' => collect($this->catalogue())
                ->map(fn (array $entry, string $key) => [
                    'key' => $key,
                    'label' => $entry['label'],
                ])
                ->values()
                ->all(),
        ]);
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

    /**
     * A member the product knows only a first name for — which, since the
     * privacy guard was narrowed, is what most recipients look like.
     */
    private function sampleRecipientWithoutSurname(): User
    {
        $user = new User([
            'name' => 'Kemar',
            'email' => 'kemar@example.com',
        ]);

        $user->id = 2;
        $user->setRelation('profile', new Profile(['first_name' => 'Kemar', 'last_name' => null]));

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
