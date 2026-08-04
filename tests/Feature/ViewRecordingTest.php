<?php

namespace Tests\Feature;

use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Support\MatchPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\ScannedFiles;
use Tests\TestCase;

/**
 * ⚠️ EVERY PATH THAT SHOWS THE UNLOCKED CONTENTS MUST RECORD THAT IT DID.
 *
 * `viewed_at` was stamped by the match SHOW page only. The DASHBOARD renders
 * the identical payload through forCard() — name, photographs, biography — and
 * stamped nothing. Since unlocking redirects onto the dashboard, a member was
 * reading an introduction the instant they paid, and the admin match log
 * reported it as never viewed.
 *
 * That is a screen the client uses to judge whether his own product is working,
 * telling him members ignore introductions they have in fact read.
 *
 * ⚠️ THE FIX IS STRUCTURAL RATHER THAN REMEMBERED. The stamp lives at the
 * emission point, so "emitted the payload without recording it" is no longer
 * expressible. Recording it at the call sites is what failed: there were two,
 * one was remembered, and nothing made the other fail.
 */
class ViewRecordingTest extends TestCase
{
    use RefreshDatabase;

    private function memberWithUnlockedMatch(): array
    {
        $me = User::factory()->create();
        $them = User::factory()->create();

        $mine = Profile::factory()->create(['user_id' => $me->id]);
        $theirs = Profile::factory()->create(['user_id' => $them->id, 'first_name' => 'Idris']);

        $match = MatchRecord::create([
            'seeker_profile_id' => $mine->id,
            'matched_profile_id' => $theirs->id,
            'total_score' => 87.25,
            'score_breakdown' => ['components' => ['affinity' => ['raw' => 92.0]]],
            'compatibility_rationale' => 'A patient, home-building match.',
            'commonalities' => ['You both put family first in your values.'],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
        ]);

        return [$me->fresh(), $match];
    }

    /** ⚠️ THE BUG. The dashboard shows everything and used to record nothing. */
    public function test_the_dashboard_records_a_view(): void
    {
        [$user, $match] = $this->memberWithUnlockedMatch();

        $this->assertNull($match->viewed_at);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertNotNull(
            $match->fresh()->viewed_at,
            'The dashboard rendered the unlocked contents and recorded nothing, so the admin log '
                .'reports this introduction as never viewed.'
        );
    }

    public function test_the_match_page_records_a_view(): void
    {
        [$user, $match] = $this->memberWithUnlockedMatch();

        $this->actingAs($user)->get("/matches/{$match->id}")->assertOk();

        $this->assertNotNull($match->fresh()->viewed_at);
    }

    /**
     * ⚠️ THE COUPLING THAT MADE THE ORIGINAL BUG LOAD-BEARING.
     *
     * Recording a dashboard view must NOT suppress the arrival ceremony on the
     * match page. While both questions shared one column, the honest fix to the
     * admin log would have meant the ring never animated for anybody — which is
     * why the wrong behaviour survived: fixing it visibly broke something else.
     */
    public function test_seeing_it_on_the_dashboard_does_not_consume_the_ceremony(): void
    {
        [$user, $match] = $this->memberWithUnlockedMatch();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertNotNull($match->fresh()->viewed_at, 'The dashboard did not record a view.');

        $payload = $this->actingAs($user->fresh())
            ->get("/matches/{$match->id}")
            ->viewData('page')['props']['match'];

        $this->assertTrue(
            $payload['firstView'],
            'The arrival ceremony was consumed by a dashboard render, so the ring never animates '
                .'for anybody. This is why the two questions need two columns.'
        );
    }

    /** And the ceremony still runs exactly once. */
    public function test_the_ceremony_runs_once(): void
    {
        [$user, $match] = $this->memberWithUnlockedMatch();

        $first = $this->actingAs($user)->get("/matches/{$match->id}")->viewData('page')['props']['match'];
        $second = $this->actingAs($user->fresh())->get("/matches/{$match->id}")->viewData('page')['props']['match'];

        $this->assertTrue($first['firstView']);
        $this->assertFalse($second['firstView'], 'The ceremony replays on every visit, which makes it a delay.');
    }

    /** A teaser shows nothing to record. */
    public function test_a_locked_teaser_records_no_view(): void
    {
        [$user, $match] = $this->memberWithUnlockedMatch();

        $match->forceFill(['status' => MatchRecord::STATUS_TEASER, 'unlocked_at' => null])->save();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertNull(
            $match->fresh()->viewed_at,
            'A locked teaser recorded a view. Nothing of the other member was shown, so there is '
                .'nothing to have seen.'
        );
    }

    /** ⚠️ The first view is kept, not overwritten by the most recent one. */
    public function test_the_first_view_is_the_one_that_is_kept(): void
    {
        [$user, $match] = $this->memberWithUnlockedMatch();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $first = $match->fresh()->viewed_at;

        $this->travel(2)->hours();

        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();

        $this->assertEquals(
            $first->toDateTimeString(),
            $match->fresh()->viewed_at->toDateTimeString(),
            'Every visit overwrites the timestamp, so the log reports when they last looked '
                .'rather than when they first saw it.'
        );
    }

    /**
     * ⚠️ THE STRUCTURAL GUARANTEE, ASSERTED.
     *
     * The stamp belongs to the presenter because the presenter is the single
     * emission point. If a controller ever writes `viewed_at` again, that is
     * the two-call-sites arrangement returning, and the next surface added will
     * forget it in exactly the same way.
     */
    public function test_no_controller_stamps_viewed_at_itself(): void
    {
        $offenders = [];

        foreach (ScannedFiles::withExtension(File::allFiles(app_path('Http/Controllers')), ['php'], 'controllers', 10) as $path) {
            $source = File::get($path);

            if (preg_match("/'viewed_at'\s*=>/", $source)) {
                $offenders[] = str_replace(base_path().'/', '', $path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "A controller writes viewed_at directly:\n  ".implode("\n  ", $offenders)
                ."\n\nIt belongs to MatchPresenter::full(), which is the emission point. Recording "
                .'it at call sites is what caused the dashboard to show introductions in full and '
                .'record nothing.'
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). The scan above is a negative assertion over
     * source text and proves nothing unless the reader can be shown a real
     * offence and a clean file.
     */
    public function test_the_stamp_scan_can_see_a_real_offence(): void
    {
        $reader = fn (string $s) => preg_match("/'viewed_at'\s*=>/", $s) === 1;

        $this->assertTrue($reader("\$match->forceFill(['viewed_at' => now()])->save();"));
        $this->assertFalse($reader("\$match->forceFill(['opened_at' => now()])->save();"));
    }

    /** Every emission of an unlocked payload records it, whichever method built it. */
    public function test_the_presenter_records_a_view_however_it_is_reached(): void
    {
        [, $match] = $this->memberWithUnlockedMatch();

        // forCard() is the dashboard's route into full().
        app(MatchPresenter::class)->forCard($match->fresh());

        $this->assertNotNull($match->fresh()->viewed_at);
    }
}
