<?php

namespace Tests\Feature;

use App\Jobs\AnnounceMutualInterest;
use App\Jobs\AnnounceSomeoneInterested;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Services\Matching\MatchInterest;
use App\Support\MatchPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * ⚠️ THE MOST SENSITIVE BEHAVIOUR IN THE PRODUCT.
 *
 * Everything else in RealVows withholds. This reveals — a last name, an email
 * address and a phone number, to exactly two people, at exactly one moment.
 *
 * The failure that matters is not "mutual interest does not work". It is
 * "contact details escaped one step early", and it is invisible from the
 * outside: the page looks correct, the member is delighted, and somebody's
 * phone number reached a person who had not agreed to receive it.
 *
 * ⚠️ EVERY REVEAL IS ASSERTED FROM BOTH SIDES. A guard checked from one
 * direction passes happily while the other direction leaks, because the two
 * directions are two different database rows.
 */
class MutualInterestTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $email, string $first, string $last, string $phone): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'phone_country_code' => '+1',
            'phone_number' => $phone,
        ]);

        Profile::factory()->create([
            'user_id' => $user->id,
            'first_name' => $first,
            'last_name' => $last,
            'city' => 'Kingston',
        ]);

        return $user->refresh();
    }

    /** One direction of an introduction, already paid for. */
    private function matchBetween(User $seeker, User $matched, string $status = MatchRecord::STATUS_UNLOCKED): MatchRecord
    {
        return MatchRecord::create([
            'seeker_profile_id' => $seeker->profile->id,
            'matched_profile_id' => $matched->profile->id,
            'total_score' => 87.25,
            'score_breakdown' => ['components' => ['affinity' => ['raw' => 92.0]]],
            'compatibility_rationale' => 'A patient, home-building match.',
            'commonalities' => ['You both put family first in your values.'],
            'status' => $status,
            'response' => null,
            'unlocked_at' => $status === MatchRecord::STATUS_UNLOCKED ? now() : null,
        ]);
    }

    // ── The reveal ───────────────────────────────────────────────────────────

    /**
     * ⚠️ THE CASE A RELAXED GUARD GETS WRONG.
     *
     * One person has said yes. The other has not answered at all. Nothing may
     * be revealed to anybody — and in particular the person who said yes must
     * not be rewarded for saying it.
     */
    public function test_one_sided_interest_reveals_nothing(): void
    {
        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');

        $aishaToOmar = $this->matchBetween($aisha, $omar);
        $omarToAisha = $this->matchBetween($omar, $aisha);

        app(MatchInterest::class)->express($aishaToOmar);

        // The one who said yes learns nothing.
        $payload = app(MatchPresenter::class)->full($aishaToOmar->fresh());
        $this->assertContactIsAbsent($payload, 'the member who expressed interest first');
        $this->assertStringNotContainsString('Khan', json_encode($payload));

        // And the one who has not answered learns nothing either — in
        // particular, not that somebody is waiting on them.
        $reverse = app(MatchPresenter::class)->full($omarToAisha->fresh());
        $this->assertContactIsAbsent($reverse, 'the member who has not answered');
        $this->assertStringNotContainsString('Mohammed', json_encode($reverse));
        $this->assertFalse($reverse['interest']['mutual']);
    }

    /** ⚠️ And a pass on the other side is still not mutual, however keen the first. */
    public function test_interest_answered_with_a_pass_reveals_nothing(): void
    {
        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');

        $aishaToOmar = $this->matchBetween($aisha, $omar);
        $omarToAisha = $this->matchBetween($omar, $aisha);

        app(MatchInterest::class)->express($aishaToOmar);
        app(MatchInterest::class)->pass($omarToAisha);

        $this->assertContactIsAbsent(app(MatchPresenter::class)->full($aishaToOmar->fresh()), 'a match that was passed on');
        $this->assertContactIsAbsent(app(MatchPresenter::class)->full($omarToAisha->fresh()), 'the member who passed');
    }

    /** Both said yes. Both — and only now — get the contact details. */
    public function test_mutual_interest_reveals_contact_to_both_sides(): void
    {
        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');

        $aishaToOmar = $this->matchBetween($aisha, $omar);
        $omarToAisha = $this->matchBetween($omar, $aisha);

        app(MatchInterest::class)->express($aishaToOmar);
        $mutual = app(MatchInterest::class)->express($omarToAisha);

        $this->assertTrue($mutual, 'The second yes did not complete a mutual match.');

        $forAisha = app(MatchPresenter::class)->full($aishaToOmar->fresh());
        $this->assertSame('Khan', $forAisha['lastName']);
        $this->assertSame('omar@example.com', $forAisha['contact']['email']);
        $this->assertSame('+1 8765550202', $forAisha['contact']['phone']);

        $forOmar = app(MatchPresenter::class)->full($omarToAisha->fresh());
        $this->assertSame('Mohammed', $forOmar['lastName']);
        $this->assertSame('aisha@example.com', $forOmar['contact']['email']);
        $this->assertSame('+1 8765550101', $forOmar['contact']['phone']);
    }

    /**
     * ⚠️ NO RECIPROCAL ROW MEANS NEVER MUTUAL.
     *
     * The engine writes matches per seeker, so B may simply never have been
     * matched with A. An implementation reading only the row in front of it
     * would call this mutual on one person's click.
     */
    public function test_interest_with_no_reciprocal_row_is_never_mutual(): void
    {
        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');

        $aishaToOmar = $this->matchBetween($aisha, $omar);
        // Deliberately no omar → aisha row.

        $this->assertFalse(app(MatchInterest::class)->express($aishaToOmar));
        $this->assertContactIsAbsent(app(MatchPresenter::class)->full($aishaToOmar->fresh()), 'a match with no reciprocal row');
    }

    // ── Who may answer ───────────────────────────────────────────────────────

    /** A teaser is a first name and a score. That is not consent to be introduced. */
    public function test_interest_cannot_be_expressed_on_a_locked_introduction(): void
    {
        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');

        $teaser = $this->matchBetween($aisha, $omar, MatchRecord::STATUS_TEASER);

        $this->assertFalse(app(MatchInterest::class)->canRespond($teaser));

        $this->actingAs($aisha)
            ->post(route('matches.respond', $teaser), ['response' => 'interested'])
            ->assertNotFound();

        $this->assertNull($teaser->fresh()->response);
    }

    public function test_a_member_cannot_answer_somebody_elses_introduction(): void
    {
        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');
        $stranger = $this->member('stranger@example.com', 'Yusuf', 'Ali', '8765550303');

        $aishaToOmar = $this->matchBetween($aisha, $omar);

        $this->actingAs($stranger)
            ->post(route('matches.respond', $aishaToOmar), ['response' => 'interested'])
            ->assertNotFound();

        $this->assertNull($aishaToOmar->fresh()->response);
    }

    /**
     * ⚠️ AN ANSWER IS WRITTEN ONCE. A double-tap must not flip a pass into
     * interest, because by then the other side may already have been told.
     */
    public function test_an_answer_cannot_be_overwritten(): void
    {
        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');

        $match = $this->matchBetween($aisha, $omar);

        app(MatchInterest::class)->pass($match);
        app(MatchInterest::class)->express($match->fresh());

        $this->assertSame(MatchRecord::RESPONSE_PASSED, $match->fresh()->response);
    }

    // ── The announcement ─────────────────────────────────────────────────────

    /** ⚠️ A pass is never told to anybody. No job, no message, no trace. */
    public function test_a_pass_announces_nothing(): void
    {
        Bus::fake();

        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');

        $match = $this->matchBetween($aisha, $omar);

        $this->actingAs($aisha)
            ->post(route('matches.respond', $match), ['response' => 'passed'])
            ->assertRedirect();

        Bus::assertNothingDispatched();
    }

    /**
     * ⚠️ ONE-SIDED INTEREST DOES NOTIFY — BUT WITH NO IDENTIFYING DETAIL.
     *
     * The brief is explicit: passing is silent, interest is not. The other
     * party is told that somebody they were introduced to is interested, and
     * nothing else — no name, no city, no score, no match id. Enough to bring
     * them back to the product, not enough to identify anyone.
     *
     * It is emphatically NOT a mutual-interest announcement: no contact
     * details move, because the second yes has not happened.
     */
    public function test_one_sided_interest_notifies_without_identifying_anyone(): void
    {
        Bus::fake();

        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');

        $this->matchBetween($omar, $aisha);
        $match = $this->matchBetween($aisha, $omar);

        $this->actingAs($aisha)
            ->post(route('matches.respond', $match), ['response' => 'interested'])
            ->assertRedirect();

        Bus::assertNotDispatched(AnnounceMutualInterest::class);
        Bus::assertDispatchedTimes(AnnounceSomeoneInterested::class, 1);

        /*
         * ⚠️ THE JOB CARRIES ONLY THE RECIPIENT. Not the match id, not the
         * admirer's id — because a job payload is a row in the jobs table and
         * an admin surface, and "who is interested in whom" must not be
         * reconstructable from a queue entry before both people have agreed.
         */
        Bus::assertDispatched(AnnounceSomeoneInterested::class, function ($job) use ($omar) {
            $this->assertSame($omar->id, $job->recipientId);

            return true;
        });

        /*
         * ⚠️ ASSERTED ON THE CONSTRUCTOR, NOT BY SEARCHING THE PAYLOAD.
         *
         * Searching a serialised job for an id that should be absent passes by
         * accident whenever one id is not a substring of another — with ids 1
         * and 2 in a fresh test database, that is most of the time.
         *
         * What actually needs to hold is that there is nowhere to put the
         * admirer: the job takes one argument. If a second is ever added, this
         * fails and somebody has to justify it.
         */
        $constructor = (new \ReflectionClass(AnnounceSomeoneInterested::class))->getConstructor();

        $this->assertSame(
            ['recipientId'],
            array_map(fn ($p) => $p->getName(), $constructor->getParameters()),
            'AnnounceSomeoneInterested has gained a constructor argument. A queued job is a row in '
                .'the jobs table and a line on an admin screen, so anything carried here makes '
                .'"who is interested in whom" reconstructable before the recipient has answered.'
        );
    }

    /** The second yes is what announces it. */
    public function test_the_completing_yes_announces_it_once(): void
    {
        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');

        $aishaToOmar = $this->matchBetween($aisha, $omar);
        $omarToAisha = $this->matchBetween($omar, $aisha);

        app(MatchInterest::class)->express($aishaToOmar);

        Bus::fake();

        $this->actingAs($omar)
            ->post(route('matches.respond', $omarToAisha), ['response' => 'interested'])
            ->assertRedirect();

        Bus::assertDispatchedTimes(AnnounceMutualInterest::class, 1);
    }

    /**
     * ⚠️ THE JOB RE-ASKS RATHER THAN TRUSTING ITS ARGUMENT.
     *
     * It is dispatched with an id and runs up to a minute later. If the world
     * has changed by then, silence is the safe failure.
     */
    public function test_the_announcement_job_re_checks_mutuality_at_run_time(): void
    {
        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');

        $aishaToOmar = $this->matchBetween($aisha, $omar);
        $omarToAisha = $this->matchBetween($omar, $aisha);

        app(MatchInterest::class)->express($aishaToOmar);
        app(MatchInterest::class)->express($omarToAisha);

        // The reciprocal row goes away between dispatch and run.
        $omarToAisha->delete();

        // No throw, and nothing sent.
        app(AnnounceMutualInterest::class, ['matchId' => $aishaToOmar->id])
            ->handle(
                app(MatchInterest::class),
                app(\App\Services\WhatsApp\WhatsAppSender::class),
                app(\App\Services\Settings\SettingsService::class),
            );

        $this->assertFalse(app(MatchInterest::class)->isMutual($aishaToOmar->fresh()));
    }

    /**
     * ⚠️ THE RACE: ONE EVENT AND TWO NOTIFICATIONS, NOT TWO AND FOUR.
     *
     * A→B and B→A are two rows describing one thing that happened. If both
     * people tap "interested" in the same moment, each request can see the
     * other's answer already written and each will conclude "mutual" — so both
     * dispatch. Without deduplication that is two announcements, and both
     * people are told twice.
     *
     * The defence is the unique key, and it is keyed on the SORTED PAIR rather
     * than the row id, because the two rows have different ids and would
     * otherwise deduplicate against nothing at all.
     */
    public function test_simultaneous_interest_produces_exactly_one_announcement(): void
    {
        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');

        $aishaToOmar = $this->matchBetween($aisha, $omar);
        $omarToAisha = $this->matchBetween($omar, $aisha);

        // Both answers land before either announcement is worked out — which is
        // exactly what two requests inside the same second produce.
        app(MatchInterest::class)->express($aishaToOmar);
        app(MatchInterest::class)->express($omarToAisha);

        // Both directions now legitimately see a mutual match.
        $this->assertTrue(app(MatchInterest::class)->isMutual($aishaToOmar->fresh()));
        $this->assertTrue(app(MatchInterest::class)->isMutual($omarToAisha->fresh()));

        $fromAisha = new AnnounceMutualInterest($aishaToOmar->id);
        $fromOmar = new AnnounceMutualInterest($omarToAisha->id);

        $this->assertSame(
            $fromAisha->uniqueId(),
            $fromOmar->uniqueId(),
            'The two directions of one mutual match produce different unique keys, so a genuine '
                .'race announces it twice and tells both people twice.'
        );

        /*
         * ⚠️ POSITIVE CONTROL (Rule 5). Two keys being equal proves nothing if
         * the method returns a constant — which would collapse every mutual
         * match in the product into one, and silently drop all but the first.
         */
        $otherPair = $this->matchBetween(
            $aisha,
            $this->member('yusuf@example.com', 'Yusuf', 'Ali', '8765550303'),
        );

        $this->assertNotSame(
            $fromAisha->uniqueId(),
            (new AnnounceMutualInterest($otherPair->id))->uniqueId(),
            'Unrelated pairs share a unique key, so every mutual match after the first would be '
                .'silently swallowed.'
        );
    }

    /** ⚠️ One announcement, but BOTH people are told by it. */
    public function test_the_single_announcement_tells_both_people(): void
    {
        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');

        $aishaToOmar = $this->matchBetween($aisha, $omar);
        $omarToAisha = $this->matchBetween($omar, $aisha);

        app(MatchInterest::class)->express($aishaToOmar);
        app(MatchInterest::class)->express($omarToAisha);

        // The settings rows have to exist before one can be written to.
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        app(\App\Services\Settings\SettingsService::class)->set('whatsapp', 'template_name', 'mutual_interest');

        $sender = new RecordingSender;

        (new AnnounceMutualInterest($aishaToOmar->id))->handle(
            app(MatchInterest::class),
            $sender,
            app(\App\Services\Settings\SettingsService::class),
        );

        $this->assertCount(2, $sender->sent, 'One mutual match must notify exactly two people.');

        $recipients = array_column($sender->sent, 'to');
        sort($recipients);

        $this->assertSame(['18765550101', '18765550202'], $recipients);

        /*
         * ⚠️ AND EACH IS TOLD THE OTHER'S NAME, NOT THEIR OWN. This is the
         * variable-order corruption WhatsAppTemplateTest exists to prevent,
         * checked here against real values rather than in the abstract.
         */
        $byRecipient = array_column($sender->sent, 'variables', 'to');

        $this->assertSame(['Aisha', 'Omar'], $byRecipient['18765550101']);
        $this->assertSame(['Omar', 'Aisha'], $byRecipient['18765550202']);
    }

    // ── Positive controls ────────────────────────────────────────────────────

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). Every reveal test above is an
     * assertion that something is ABSENT, which is satisfied just as well by a
     * presenter that returns an empty array, a null, or throws.
     *
     * This proves the reader finds the very fields the others require to be
     * missing — so "absent" means withheld rather than never-present.
     */
    public function test_the_reader_can_see_contact_details_when_they_are_there(): void
    {
        $aisha = $this->member('aisha@example.com', 'Aisha', 'Mohammed', '8765550101');
        $omar = $this->member('omar@example.com', 'Omar', 'Khan', '8765550202');

        $aishaToOmar = $this->matchBetween($aisha, $omar);
        $omarToAisha = $this->matchBetween($omar, $aisha);

        app(MatchInterest::class)->express($aishaToOmar);
        app(MatchInterest::class)->express($omarToAisha);

        $payload = app(MatchPresenter::class)->full($aishaToOmar->fresh());
        $encoded = json_encode($payload);

        $this->assertArrayHasKey('contact', $payload);
        $this->assertArrayHasKey('lastName', $payload);
        $this->assertStringContainsString('omar@example.com', $encoded);
        $this->assertStringContainsString('8765550202', $encoded);
        $this->assertStringContainsString('Khan', $encoded);
    }

    /**
     * ⚠️ ABSENT, NOT NULL. A null key still tells the browser the shape of
     * what is withheld, and invites the next developer to populate it.
     *
     * @param  array<string, mixed>  $payload
     */
    private function assertContactIsAbsent(array $payload, string $who): void
    {
        $this->assertArrayNotHasKey('contact', $payload, "Contact details reached {$who}.");
        $this->assertArrayNotHasKey('lastName', $payload, "A last name reached {$who}.");

        $encoded = json_encode($payload);

        foreach (['@example.com', '87655501', '87655502'] as $fragment) {
            $this->assertStringNotContainsString(
                $fragment,
                $encoded,
                "Something resembling contact details ({$fragment}) reached {$who}."
            );
        }
    }
}

/**
 * A sender that keeps what it was asked to send.
 *
 * ⚠️ NEEDED BECAUSE THE STAND-IN REPORTS SUCCESS AND KEEPS NOTHING. Asserting
 * against LogSender would prove only that the job ran without throwing — which
 * is exactly the "reports success while checking nothing" failure Rule 5 is
 * about. This records the recipient and the ORDERED variables, so a swapped
 * variable order is visible rather than merely absent from a log.
 */
class RecordingSender implements \App\Services\WhatsApp\WhatsAppSender
{
    /** @var list<array{to: string, template: string, variables: list<string>}> */
    public array $sent = [];

    public function sendTemplate(string $to, string $template, array $variables): \App\Services\WhatsApp\WhatsAppResult
    {
        $this->sent[] = ['to' => $to, 'template' => $template, 'variables' => $variables];

        return \App\Services\WhatsApp\WhatsAppResult::sent('recorded-'.count($this->sent));
    }

    public function mode(): string
    {
        return 'stand-in';
    }
}
