<?php

namespace Tests\Feature;

use App\Mail\IntroductionDelivered;
use App\Mail\IntroductionsUnlocked;
use App\Mail\MutualInterest;
use App\Mail\SomeoneInterested;
use App\Mail\TeaserExpiring;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Scanners;
use Tests\TestCase;

/**
 * ⚠️ EVERY ASSERTION HERE IS AGAINST RENDERED OUTPUT, NOT TEMPLATE SOURCE.
 *
 * A template file can be perfectly clean and still produce a forbidden word,
 * because the word arrives through an interpolated variable — a rationale
 * written by the engine, a name, a reason typed by an administrator. Scanning
 * the Blade file proves only that the literal text is safe, which is the part
 * nobody was ever going to get wrong.
 *
 * This is the same distinction that made the last-name audit wrong: reading
 * what is stored rather than what is emitted.
 */
class MailableContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function member(): User
    {
        $user = User::factory()->create(['email' => 'aisha@example.com']);

        Profile::factory()->create([
            'user_id' => $user->id,
            'first_name' => 'Aisha',
            'last_name' => 'Mohammed-Bakare',
        ]);

        return $user->fresh();
    }

    /**
     * All five, with realistic data — the rationale here is deliberately the
     * longest, most natural-language field in the product.
     *
     * @return array<string, \App\Mail\MemberMailable>
     */
    private function allFive(User $user): array
    {
        return [
            'introduction-delivered' => new IntroductionDelivered(
                $user,
                count: 1,
                score: 87,
                rationale: 'You are both settled in Kingston, both want children within a few '
                    .'years, and you each named family and faith as the things that matter most. '
                    .'Neither of you is in a hurry, which is rarer than it sounds.',
            ),
            'introductions-unlocked' => new IntroductionsUnlocked($user, count: 3),
            'mutual-interest' => new MutualInterest($user, otherFirstName: 'Omar'),
            'someone-interested' => new SomeoneInterested($user),
            'teaser-expiring' => new TeaserExpiring($user, count: 2, daysLeft: 7),
        ];
    }

    // ── The vocabulary, over rendered output ─────────────────────────────────

    public function test_no_rendered_email_contains_affinity_vocabulary(): void
    {
        $user = $this->member();
        $offences = [];

        foreach ($this->allFive($user) as $key => $mailable) {
            $html = Scanners::prose($mailable->render());

            foreach (Scanners::forbiddenTerms($html) as $term) {
                $offences[] = "{$key} (html) → {$term}";
            }
        }

        $this->assertSame([], $offences, implode("\n  ", $offences));
    }

    /** ⚠️ POSITIVE CONTROL: the reader can see a term in a rendered mailable. */
    public function test_the_reader_would_catch_a_term_arriving_through_a_variable(): void
    {
        $user = $this->member();

        // A rationale that a careless engine change could produce. The template
        // file is untouched and completely clean.
        $poisoned = new IntroductionDelivered(
            $user,
            count: 1,
            score: 87,
            rationale: 'Her birth chart suggests a Leo, which pairs well with your sign.',
        );

        $found = Scanners::forbiddenTerms(Scanners::prose($poisoned->render()));

        $this->assertNotEmpty(
            $found,
            'The reader cannot see forbidden vocabulary that arrives through an interpolated '
                .'variable, so scanning rendered output proves no more than scanning the template.'
        );

        $this->assertContains('birth chart', $found);
        $this->assertContains('leo', $found);
    }

    // ── No photographs, ever ─────────────────────────────────────────────────

    /**
     * ⚠️ EMAIL IS NOT A PRIVATE CHANNEL.
     *
     * It sits in a preview pane on a shared laptop, on a lock screen at a
     * family dinner, in a webmail account somebody else has the password to. A
     * locked introduction stays locked; an unlocked one is unlocked behind the
     * link, not in the notification.
     *
     * A photograph in an email is also permanent in a way the product is not —
     * a member who deletes their account cannot delete the copy sitting in
     * fifty inboxes.
     */
    public function test_no_email_carries_a_photograph_of_another_member(): void
    {
        $user = $this->member();
        $offences = [];

        foreach ($this->allFive($user) as $key => $mailable) {
            $html = $mailable->render();

            foreach (Scanners::photoPaths($html) as $path) {
                $offences[] = "{$key} → {$path}";
            }

            // Any <img> at all is worth failing on: the only images these
            // emails need are none.
            if (preg_match('/<img[^>]+src=/i', $html)) {
                $offences[] = "{$key} contains an <img> tag";
            }
        }

        $this->assertSame([], $offences, implode("\n  ", $offences));
    }

    /** ⚠️ POSITIVE CONTROL: the photo reader can see a real photo path. */
    public function test_the_photo_reader_can_see_a_real_path(): void
    {
        $this->assertNotEmpty(
            Scanners::photoPaths('<img src="https://realvows.skillleo.com/photos/12?signature=abc">'),
            'The photo reader is blind, so "no photographs in email" proves nothing.'
        );

        $this->assertSame([], Scanners::photoPaths('<p>No images here at all.</p>'));
    }

    // ── The plain-text part ──────────────────────────────────────────────────

    /**
     * ⚠️ A MISSING TEXT PART IS A DELIVERABILITY PROBLEM, NOT A NICETY.
     *
     * A multipart message with no text alternative scores measurably worse with
     * spam filters. On an established domain that is survivable; on a brand-new
     * one with no reputation to spend it is the difference between the inbox
     * and the junk folder for the first few hundred sends — which are exactly
     * the sends that establish the reputation.
     */
    public function test_every_email_has_a_plain_text_part(): void
    {
        $user = $this->member();

        foreach ($this->allFive($user) as $key => $mailable) {
            $this->assertNotNull(
                $mailable->content()->text,
                "[{$key}] has no plain-text alternative."
            );

            $rendered = view($mailable->content()->text, $mailable->content()->with)->render();

            $this->assertNotEmpty(trim($rendered), "[{$key}]'s text part rendered empty.");
        }
    }

    /**
     * ⚠️ THE TEXT PART IS PROSE, NOT STRIPPED HTML.
     *
     * A text part produced by removing tags reads as debris — orphan link text,
     * doubled headings, "Open your dashboard" with no URL beside it — and a
     * spam filter can tell the difference between that and a written message.
     */
    public function test_the_text_part_is_written_prose_and_carries_the_link_as_a_url(): void
    {
        $user = $this->member();

        foreach ($this->allFive($user) as $key => $mailable) {
            $text = view($mailable->content()->text, $mailable->content()->with)->render();

            $this->assertStringNotContainsString('<', $text, "[{$key}]'s text part contains markup.");
            $this->assertStringNotContainsString('&nbsp;', $text, "[{$key}]'s text part contains entities.");

            // A bare URL, because there is nothing to hyperlink in plain text.
            $this->assertMatchesRegularExpression(
                '#https?://#',
                $text,
                "[{$key}]'s text part has no URL, so the reader has nowhere to go."
            );

            // And the unsubscribe line, spelled out.
            $this->assertStringContainsString(
                'To stop receiving these emails:',
                $text,
                "[{$key}]'s text part has no unsubscribe instruction."
            );
        }
    }

    // ── Privacy of the notification itself ───────────────────────────────────

    /**
     * ⚠️ `someone_interested` NAMES NOBODY. The other member has said yes and
     * this one has not answered — naming them discloses a one-sided interest
     * that was never consented to, and lets the recipient work out who passed.
     */
    public function test_the_someone_interested_email_identifies_nobody(): void
    {
        $user = $this->member();

        $html = (new SomeoneInterested($user))->render();

        foreach (['Omar', 'Kingston', '87', 'out of 100'] as $detail) {
            $this->assertStringNotContainsString(
                $detail,
                $html,
                "The someone-interested email carries [{$detail}], which identifies or "
                    .'characterises the other member.'
            );
        }

        // It does address the recipient by name — that is their own.
        $this->assertStringContainsString('Aisha', $html);
    }

    /** ⚠️ Only the mutual-interest email may name another member. */
    public function test_only_the_mutual_interest_email_names_another_member(): void
    {
        $user = $this->member();

        foreach ($this->allFive($user) as $key => $mailable) {
            $html = $mailable->render();

            if ($key === 'mutual-interest') {
                $this->assertStringContainsString('Omar', $html);

                continue;
            }

            $this->assertStringNotContainsString(
                'Omar',
                $html,
                "[{$key}] names another member. Only mutual-interest may, and only because both "
                    .'people have consented by then.'
            );
        }
    }

    /** Every email carries a working unsubscribe link. */
    public function test_every_email_carries_a_signed_unsubscribe_link(): void
    {
        $user = $this->member();

        foreach ($this->allFive($user) as $key => $mailable) {
            $this->assertStringContainsString(
                '/unsubscribe/',
                $mailable->render(),
                "[{$key}] has no unsubscribe link, which is a spam complaint waiting to happen."
            );

            $this->assertStringContainsString('signature=', $mailable->render());
        }
    }

    /** ⚠️ The product never shouts. Not even about good news. */
    public function test_no_email_shouts(): void
    {
        $user = $this->member();

        foreach ($this->allFive($user) as $key => $mailable) {
            $this->assertStringNotContainsString(
                '!',
                strip_tags($mailable->render()),
                "[{$key}] contains an exclamation mark. This is a marriage platform, not a sale."
            );
        }
    }
}
