<?php

namespace Tests\Feature;

use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\ProfilePreference;
use App\Models\Report;
use App\Models\User;
use App\Models\Verification;
use App\Services\Credits\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The payoff screen — what the member actually paid for.
 */
class MatchViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    /**
     * @return array{0: User, 1: MatchRecord, 2: Profile}
     */
    private function openedMatch(array $themState = []): array
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $mine = Profile::factory()->woman()->create(['user_id' => $user->id]);
        $them = Profile::factory()->man()->create($themState);
        Verification::factory()->verified()->create(['profile_id' => $them->id]);
        ProfilePreference::factory()->create(['profile_id' => $them->id]);

        $match = MatchRecord::create([
            'seeker_profile_id' => $mine->id,
            'matched_profile_id' => $them->id,
            'total_score' => 88.97,
            'score_breakdown' => ['components' => ['values' => ['raw' => 92]]],
            'compatibility_rationale' => 'A patient, home-building match.',
            'commonalities' => ['You both put family first in your values.'],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        app(CreditService::class)->adjust($user, 1, 'goodwill', 'test', $user);
        app(CreditService::class)->unlockMatch($user->fresh(), $match);

        return [$user->fresh(), $match->fresh(), $them];
    }

    private function payload(User $user, MatchRecord $match): array
    {
        return $this->actingAs($user)->get("/matches/{$match->id}")->viewData('page')['props']['match'];
    }

    // ── Access ───────────────────────────────────────────────────────────

    public function test_an_opened_introduction_renders(): void
    {
        [$user, $match] = $this->openedMatch();

        $this->actingAs($user)->get("/matches/{$match->id}")->assertOk();
    }

    /** A paywall would confirm the person exists and invite URL-guessing. */
    public function test_a_teaser_answers_404_rather_than_a_paywall(): void
    {
        [$user, $match] = $this->openedMatch();

        $match->forceFill(['status' => MatchRecord::STATUS_TEASER])->save();

        $response = $this->actingAs($user)->get("/matches/{$match->id}");

        $response->assertNotFound();
        $this->assertNotSame(403, $response->getStatusCode());
    }

    // ── The score ────────────────────────────────────────────────────────

    /** ⚠️ 89, never 88.97, never "89%". */
    public function test_the_score_renders_as_a_whole_number(): void
    {
        [$user, $match] = $this->openedMatch();

        $payload = $this->payload($user, $match);

        $this->assertSame(89, $payload['score']);
        $this->assertIsInt($payload['score']);

        // And the stored precision is untouched.
        $this->assertSame('88.97', (string) $match->fresh()->total_score);
    }

    /**
     * ⚠️ The ceremony runs once. A count-up that replays on every visit is a
     * delay, not an arrival.
     */
    public function test_the_ceremony_is_flagged_on_the_first_visit_only(): void
    {
        [$user, $match] = $this->openedMatch();

        $this->assertTrue($this->payload($user, $match)['firstView'], 'The first visit should perform.');
        $this->assertFalse($this->payload($user, $match->fresh())['firstView'], 'A revisit replayed the ceremony.');
        $this->assertNotNull($match->fresh()->viewed_at);
    }

    // ── Empty-field discipline ───────────────────────────────────────────

    /**
     * ⚠️ A profile with gaps must read as a person who chose not to answer,
     * never as a broken page. No label is ever rendered with nothing under it.
     */
    public function test_a_row_with_no_value_is_never_emitted(): void
    {
        [$user, $match] = $this->openedMatch([
            'eye_colour' => null,
            'hair_colour' => null,
            'nationality' => null,
            'industry' => null,
        ]);

        foreach ($this->payload($user, $match)['sections'] as $section) {
            foreach ($section['rows'] as $label => $value) {
                $this->assertNotNull($value, "[{$label}] was emitted with a null value.");
                $this->assertNotSame('', trim((string) $value), "[{$label}] was emitted empty.");
            }
        }
    }

    public function test_a_section_with_nothing_in_it_is_never_emitted(): void
    {
        // A profile with nothing at all in the Physical section.
        [$user, $match] = $this->openedMatch([
            'height_cm' => null, 'body_type' => null, 'eye_colour' => null, 'hair_colour' => null,
        ]);

        $headings = array_column($this->payload($user, $match)['sections'], 'heading');

        $this->assertNotContains('Physical', $headings);
        $this->assertNotEmpty($headings, 'Every section vanished, which means the filter is too aggressive.');
    }

    public function test_the_sections_follow_the_builder_order(): void
    {
        [$user, $match] = $this->openedMatch();

        $headings = array_column($this->payload($user, $match)['sections'], 'heading');

        // Photos and Account are deliberately never sections.
        $this->assertNotContains('Photos', $headings);
        $this->assertNotContains('Account', $headings);

        $expected = array_values(array_intersect(
            ['Personal', 'Physical', 'Lifestyle', 'Values', 'Preferences'],
            $headings
        ));

        $this->assertSame($expected, $headings, 'Sections are out of builder order.');
    }

    // ── The mechanism still does not leak ────────────────────────────────

    public function test_the_payoff_screen_never_carries_the_breakdown(): void
    {
        [$user, $match] = $this->openedMatch();

        $json = json_encode($this->payload($user, $match), JSON_UNESCAPED_SLASHES);

        $this->assertStringNotContainsString('score_breakdown', $json);
        $this->assertStringNotContainsString('affinity', $json);
        $this->assertStringNotContainsString('components', $json);
    }

    /** Positive control: the search would find those strings if present. */
    public function test_the_leak_search_can_find_the_terms_when_they_are_present(): void
    {
        $json = json_encode(['score_breakdown' => ['components' => ['affinity' => 1]]], JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('score_breakdown', $json);
        $this->assertStringContainsString('affinity', $json);
        $this->assertStringContainsString('components', $json);
    }

    // ── Reporting ────────────────────────────────────────────────────────

    public function test_a_member_can_report_somebody_they_were_introduced_to(): void
    {
        [$user, $match] = $this->openedMatch();

        $this->actingAs($user)
            ->post("/matches/{$match->id}/report", [
                'reason' => 'appears_to_be_scam',
                'note' => 'Asked me for money.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_profile_id' => $user->profile->id,
            'subject_profile_id' => $match->matched_profile_id,
            'reason' => 'appears_to_be_scam',
            'status' => Report::STATUS_OPEN,
        ]);
    }

    /** Tapping twice is not two concerns, and a duplicate queue is unworkable. */
    public function test_reporting_the_same_person_twice_leaves_one_open_report(): void
    {
        [$user, $match] = $this->openedMatch();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->post("/matches/{$match->id}/report", ['reason' => 'other']);
        }

        $this->assertSame(1, Report::where('reporter_profile_id', $user->profile->id)->count());
    }

    /** And the member is told the same thing either way. */
    public function test_a_duplicate_report_is_still_acknowledged_warmly(): void
    {
        [$user, $match] = $this->openedMatch();

        $this->actingAs($user)->post("/matches/{$match->id}/report", ['reason' => 'other']);

        $this->actingAs($user)
            ->post("/matches/{$match->id}/report", ['reason' => 'other'])
            ->assertSessionHas('success', 'Thank you. Someone will look at this.');
    }

    public function test_a_member_cannot_report_somebody_they_were_never_introduced_to(): void
    {
        [, $match] = $this->openedMatch();

        $stranger = User::factory()->create();
        $stranger->assignRole('member');
        Profile::factory()->woman()->create(['user_id' => $stranger->id]);

        $this->actingAs($stranger->fresh())
            ->post("/matches/{$match->id}/report", ['reason' => 'other'])
            ->assertNotFound();

        $this->assertSame(0, Report::count());
    }

    public function test_a_report_requires_a_reason(): void
    {
        [$user, $match] = $this->openedMatch();

        $this->actingAs($user)
            ->post("/matches/{$match->id}/report", ['note' => 'something'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, Report::count());
    }

    /**
     * ⚠️ The reported member is never told anything — not that a report
     * exists, not who made it. A system that leaks back to its subject
     * invites retaliation against somebody who did the right thing.
     */
    public function test_the_reported_member_is_told_nothing(): void
    {
        [$user, $match, $them] = $this->openedMatch();

        $this->actingAs($user)->post("/matches/{$match->id}/report", ['reason' => 'appears_to_be_scam']);

        $theirUser = $them->user;
        $theirProfile = Profile::factory()->woman()->create();

        $reciprocal = MatchRecord::create([
            'seeker_profile_id' => $them->id,
            'matched_profile_id' => $theirProfile->id,
            'total_score' => 80,
            'score_breakdown' => [],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        $payload = json_encode(
            $this->actingAs($theirUser)->get('/dashboard')->viewData('page')['props'],
            JSON_UNESCAPED_SLASHES
        );

        $this->assertStringNotContainsString('report', strtolower($payload));
    }
}
