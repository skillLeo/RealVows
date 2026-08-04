<?php

namespace Tests\Feature;

use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ THIS IS THE SCREEN WHERE BROWSING WOULD CREEP BACK IN.
 *
 * The product's central promise is that the engine chooses and the member never
 * searches. A list screen is where that promise erodes, because lists invite
 * search boxes and a search box looks like a helpful addition rather than a
 * reversal of what the product is.
 *
 * Filtering by state reorganises rows the engine already chose for this one
 * member. Filtering by attribute would surface people it did not choose. The
 * first is navigation; the second is a directory.
 */
class InboxTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        Profile::factory()->verified()->create(['user_id' => $user->id, 'submitted_at' => now()]);

        return $user->fresh();
    }

    private function match(User $seeker, string $status, ?string $response = null, ?User $with = null): MatchRecord
    {
        $them = $with ?? $this->member();

        return MatchRecord::create([
            'seeker_profile_id' => $seeker->profile->id,
            'matched_profile_id' => $them->profile->id,
            'total_score' => 80,
            'score_breakdown' => ['components' => []],
            'compatibility_rationale' => 'A steady match.',
            'commonalities' => [],
            'status' => $status,
            'response' => $response,
            'unlocked_at' => $status === MatchRecord::STATUS_UNLOCKED ? now() : null,
            'generated_at' => now(),
        ]);
    }

    private function inbox(User $user, string $query = ''): array
    {
        return $this->actingAs($user)->get('/introductions'.$query)->viewData('page')['props'];
    }

    // ── The anti-browsing guarantee ──────────────────────────────────────────

    /**
     * ⚠️ THE LOAD-BEARING TEST. An attribute in the query string must change
     * nothing at all — not be honoured, not error, not partially apply.
     */
    public function test_attribute_filters_in_the_query_string_are_ignored(): void
    {
        $user = $this->member();
        $this->match($user, MatchRecord::STATUS_TEASER);
        $this->match($user, MatchRecord::STATUS_UNLOCKED);

        $plain = $this->inbox($user);

        foreach ([
            '?city=Kingston',
            '?age_min=25&age_max=30',
            '?search=Aisha',
            '?gender=woman',
            '?q=teacher',
            '?order_by=age',
        ] as $attempt) {
            $filtered = $this->inbox($user, $attempt);

            $this->assertSame(
                array_column($plain['matches'], 'id'),
                array_column($filtered['matches'], 'id'),
                "[{$attempt}] changed the result set. Filtering by attribute is browsing, and "
                    .'browsing is the one thing this product promises never to be.'
            );
        }
    }

    /** ⚠️ POSITIVE CONTROL: a STATE filter genuinely does change the results. */
    public function test_the_state_filter_actually_filters(): void
    {
        $user = $this->member();
        $waiting = $this->match($user, MatchRecord::STATUS_TEASER);
        $open = $this->match($user, MatchRecord::STATUS_UNLOCKED);

        $all = array_column($this->inbox($user)['matches'], 'id');

        $this->assertContains($waiting->id, $all);
        $this->assertContains($open->id, $all);

        $onlyWaiting = array_column($this->inbox($user, '?state=waiting')['matches'], 'id');

        $this->assertSame(
            [$waiting->id],
            $onlyWaiting,
            'The state filter changes nothing, so the test above passes for the wrong reason — '
                .'it would pass against a controller that ignores every parameter.'
        );
    }

    /** An unknown state falls back to everything rather than erroring or emptying. */
    public function test_an_unknown_state_shows_everything(): void
    {
        $user = $this->member();
        $this->match($user, MatchRecord::STATUS_TEASER);

        $props = $this->inbox($user, '?state=nonsense');

        $this->assertSame('all', $props['state']);
        $this->assertCount(1, $props['matches']);
    }

    // ── Ownership ────────────────────────────────────────────────────────────

    /** ⚠️ Nobody else's introductions, ever, under any parameter. */
    public function test_the_inbox_contains_only_this_members_introductions(): void
    {
        $mine = $this->member();
        $stranger = $this->member();

        $ours = $this->match($mine, MatchRecord::STATUS_UNLOCKED);
        $theirs = $this->match($stranger, MatchRecord::STATUS_UNLOCKED);

        $ids = array_column($this->inbox($mine)['matches'], 'id');

        $this->assertContains($ours->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    // ── States ───────────────────────────────────────────────────────────────

    public function test_each_state_selects_what_it_says(): void
    {
        $user = $this->member();

        $waiting = $this->match($user, MatchRecord::STATUS_TEASER);
        $open = $this->match($user, MatchRecord::STATUS_UNLOCKED);
        $interested = $this->match($user, MatchRecord::STATUS_UNLOCKED, MatchRecord::RESPONSE_INTERESTED);
        $passed = $this->match($user, MatchRecord::STATUS_UNLOCKED, MatchRecord::RESPONSE_PASSED);
        $closed = $this->match($user, MatchRecord::STATUS_EXPIRED);

        foreach ([
            'waiting' => $waiting,
            'open' => $open,
            'interested' => $interested,
            'passed' => $passed,
            'closed' => $closed,
        ] as $state => $expected) {
            $ids = array_column($this->inbox($user, "?state={$state}")['matches'], 'id');

            $this->assertSame([$expected->id], $ids, "State [{$state}] selected the wrong rows.");
        }
    }

    /** ⚠️ An answered introduction is no longer "open" — it has moved on. */
    public function test_an_answered_introduction_leaves_the_open_state(): void
    {
        $user = $this->member();
        $this->match($user, MatchRecord::STATUS_UNLOCKED, MatchRecord::RESPONSE_INTERESTED);

        $this->assertSame([], $this->inbox($user, '?state=open')['matches']);
    }

    public function test_the_counts_match_the_rows(): void
    {
        $user = $this->member();
        $this->match($user, MatchRecord::STATUS_TEASER);
        $this->match($user, MatchRecord::STATUS_TEASER);
        $this->match($user, MatchRecord::STATUS_UNLOCKED);

        $counts = $this->inbox($user)['states'];

        $this->assertSame(3, $counts['all']);
        $this->assertSame(2, $counts['waiting']);
        $this->assertSame(1, $counts['open']);
    }

    // ── Privacy carries over ─────────────────────────────────────────────────

    /** ⚠️ The same guard as the dashboard: a locked card carries no photo. */
    public function test_a_locked_card_in_the_inbox_still_carries_no_photo(): void
    {
        $user = $this->member();
        $this->match($user, MatchRecord::STATUS_TEASER);

        $card = $this->inbox($user)['matches'][0];

        $this->assertArrayNotHasKey('photos', $card);
        $this->assertArrayNotHasKey('lastName', $card);
    }

    /** ⚠️ And there is no search field anywhere on the page. */
    public function test_the_page_offers_no_search_input(): void
    {
        $source = \Illuminate\Support\Facades\File::get(resource_path('js/Pages/Inbox.vue'));

        foreach (['type="search"', 'type="text"', 'v-model="search"', '<input'] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $source,
                "The inbox has gained an input. A text field on this screen turns a list of "
                    .'chosen introductions into a directory.'
            );
        }

        // Positive control: the reader can see an input when there is one.
        $this->assertStringContainsString('<input', '<input type="search">');
    }
}
