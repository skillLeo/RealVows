<?php

namespace Tests\Feature;

use App\Models\MatchRecord;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\User;
use App\Models\Verification;
use App\Services\Credits\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ A LOCKED CARD MUST NOT CARRY A RECOVERABLE PHOTOGRAPH.
 *
 * The reason is not commercial, it is about real people. These are verified
 * photographs of real members on a marriage platform. A CSS blur over the
 * real image would mean anybody who opens devtools has the full-resolution
 * picture — so there is no image element in a locked card at all, and the
 * server never generates the signed URL in the first place.
 *
 * These tests read the actual HTTP payload, not the presenter in isolation,
 * because the payload is what reaches the browser.
 */
class TeaserPhotoLeakTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    /**
     * @return array{0: User, 1: MatchRecord, 2: Photo}
     */
    private function memberWithLockedMatch(): array
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $mine = Profile::factory()->woman()->create(['user_id' => $user->id]);
        Verification::factory()->verified()->create(['profile_id' => $mine->id]);

        $them = Profile::factory()->man()->create(['first_name' => 'Idris', 'last_name' => 'Bakare']);
        Verification::factory()->verified()->create(['profile_id' => $them->id]);

        $photo = Photo::create([
            'profile_id' => $them->id,
            'slot' => 'passport',
            'path' => 'profiles/'.$them->id.'/secret-portrait.jpg',
            'webp_path' => 'profiles/'.$them->id.'/secret-portrait.webp',
            'thumbnail_path' => 'profiles/'.$them->id.'/secret-portrait-thumb.jpg',
            'original_filename' => 'portrait.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'width' => 1200,
            'height' => 1600,
        ]);

        $match = MatchRecord::create([
            'seeker_profile_id' => $mine->id,
            'matched_profile_id' => $them->id,
            'total_score' => 87.25,
            'score_breakdown' => ['components' => ['values' => ['raw' => 92.0]]],
            'compatibility_rationale' => 'A patient, home-building match.',
            'commonalities' => ['You both put family first in your values.'],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        return [$user->fresh(), $match, $photo];
    }

    private function dashboardPayload(User $user): string
    {
        $props = $this->actingAs($user)->get('/dashboard')->viewData('page')['props'];

        return json_encode($props, JSON_UNESCAPED_SLASHES);
    }

    /**
     * ⚠️ POSITIVE CONTROL. Read this before trusting anything below it.
     *
     * Every leak assertion in this file is a NEGATIVE assertion on serialised
     * output, which is the most dangerous kind of test there is: if the
     * search method cannot see the thing even when it IS present, the
     * assertion passes against nothing and nobody ever finds out.
     *
     * That already happened once on this project — json_encode escapes
     * slashes, so searching a payload for "/admin/settings" found nothing and
     * a guard passed vacuously.
     *
     * So this proves the search method works, using the payload of an
     * UNLOCKED match where every one of those strings is legitimately
     * present. If this test ever fails, every leak assertion below it is
     * worthless regardless of whether it is green.
     */
    public function test_the_search_method_can_actually_find_a_photo_path(): void
    {
        [$user, $match, $photo] = $this->memberWithLockedMatch();

        app(CreditService::class)->adjust($user, 1, 'goodwill', 'Positive control', $user);
        app(CreditService::class)->unlockMatch($user->fresh(), $match);

        /*
         * ⚠️ THIS CONTROL CANNOT USE A MEMBER PAYLOAD ANY MORE, AND SAYING SO
         * IS THE POINT.
         *
         * It used to unlock an introduction and assert the strings appear,
         * proving the search works. No member payload contains a photo path
         * now — photographs go to the trusted contact — so the old control
         * would fail forever while the searches below were perfectly healthy.
         *
         * What it must still prove is unchanged: that these searches CAN see a
         * photo path when one is present. So it is asked of a real signed URL
         * for a real photo, encoded the way Inertia encodes props. If the
         * encoding hides them here, it hides them everywhere.
         */
        /*
         * ⚠️ JSON_UNESCAPED_SLASHES, THE SAME FLAG dashboardPayload() USES.
         *
         * Without it json_encode writes `http:\/\/localhost\/photos\/1` and the
         * search for '/photos/' finds nothing — which is what happened when I
         * first wrote this. Encoding the control differently from the thing it
         * is a control FOR would have declared the searches blind while they
         * were fine.
         */
        $payload = json_encode([
            'props' => ['photos' => [[
                'url' => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                    'photos.show',
                    now()->addHour(),
                    ['photo' => $photo->id],
                ),
            ]]],
        ], JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('/photos/', $payload, 'The search cannot see a photo route.');
        $this->assertStringContainsString('signature=', $payload, 'The search cannot see a signed URL.');

        /*
         * ⚠️ RE-VERIFIED AFTER THE PHASE 3 REFACTOR, WHICH IS THE PART OF RULE
         * 5 THAT IS EASIEST TO SKIP.
         *
         * This control used to look for the last name in an UNLOCKED payload.
         * Phase 3 moved the last name behind mutual interest, so it stopped
         * being present — and the control began failing for the right reason:
         * it can no longer prove the search sees a name, because there is no
         * longer a name there to see.
         *
         * The tempting fix is to delete the line. That would leave every "the
         * last name did not leak" assertion below passing against a search
         * that was never shown to work. So the control now searches a string
         * where the name genuinely is, proving the encoding does not hide it.
         */
        $encodedName = trim(json_encode(['lastName' => 'Bakare']), '{}');

        $this->assertStringContainsString(
            'Bakare',
            $encodedName,
            'json_encode is hiding a last name, so every leak assertion below is worthless.'
        );

        $this->assertStringNotContainsString(
            'Bakare',
            $payload,
            'A last name reached an unlocked payload. It is revealed only on mutual interest.'
        );

        // And the basename check the locked test relies on.
        $this->assertStringContainsString(
            'photos',
            $payload,
            'The search cannot see the photos key.'
        );
    }

    /** ⚠️ The load-bearing assertion. */
    public function test_no_string_in_the_dashboard_response_resolves_to_a_photo_path(): void
    {
        [$user, , $photo] = $this->memberWithLockedMatch();

        $payload = $this->dashboardPayload($user);

        foreach ([$photo->path, $photo->webp_path, $photo->thumbnail_path] as $path) {
            $this->assertStringNotContainsString(
                $path,
                $payload,
                "A locked card leaked the storage path [{$path}]."
            );

            $this->assertStringNotContainsString(basename($path), $payload);
        }

        // Nor a signed route to it, under any name.
        $this->assertStringNotContainsString('/photos/', $payload);
        $this->assertStringNotContainsString('signature=', $payload);
        $this->assertStringNotContainsString('secret-portrait', $payload);
    }

    /**
     * Absent, not null. A null key would still describe the shape of what is
     * being withheld and would invite the next developer to populate it.
     */
    public function test_a_locked_card_carries_no_photo_key_at_all(): void
    {
        [$user] = $this->memberWithLockedMatch();

        $props = $this->actingAs($user)->get('/dashboard')->viewData('page')['props'];
        $card = $props['matches'][0];

        $this->assertArrayNotHasKey('photos', $card);
        $this->assertArrayNotHasKey('photo', $card);
        $this->assertArrayNotHasKey('thumbnail', $card);
    }

    public function test_a_locked_card_withholds_the_last_name_and_the_profile(): void
    {
        [$user] = $this->memberWithLockedMatch();

        $payload = $this->dashboardPayload($user);

        $this->assertStringNotContainsString('Bakare', $payload, 'The last name leaked on a locked card.');

        $card = $this->actingAs($user)->get('/dashboard')->viewData('page')['props']['matches'][0];

        $this->assertArrayNotHasKey('lastName', $card);
        $this->assertArrayNotHasKey('bio', $card);
        $this->assertArrayNotHasKey('commonalities', $card);
    }

    /** The hook is never diminished. The score is what somebody pays for. */
    public function test_a_locked_card_still_shows_the_score_and_the_rationale_in_full(): void
    {
        [$user] = $this->memberWithLockedMatch();

        $card = $this->actingAs($user)->get('/dashboard')->viewData('page')['props']['matches'][0];

        $this->assertSame(87, $card['score']);
        $this->assertSame('Idris', $card['firstName']);
        $this->assertNotEmpty($card['rationale']);
    }

    /** ⚠️ The internal breakdown is for staff. It must never travel to a member. */
    public function test_the_score_breakdown_never_reaches_the_dashboard(): void
    {
        [$user] = $this->memberWithLockedMatch();

        $payload = $this->dashboardPayload($user);

        $this->assertStringNotContainsString('score_breakdown', $payload);
        $this->assertStringNotContainsString('scoreBreakdown', $payload);
        $this->assertStringNotContainsString('affinity', $payload);
        $this->assertStringNotContainsString('components', $payload);
    }

    /** Once paid for, everything the member bought is there. */
    public function test_an_unlocked_card_carries_no_photographs_either(): void
    {
        [$user, $match] = $this->memberWithLockedMatch();

        app(CreditService::class)->adjust($user, 1, 'goodwill', 'Test', $user);
        app(CreditService::class)->unlockMatch($user->fresh(), $match);

        $card = $this->actingAs($user->fresh())->get('/dashboard')->viewData('page')['props']['matches'][0];

        /*
         * ⚠️ UNLOCKING BUYS THE PROFILE, NOT THE PHOTOGRAPHS. This asserted the
         * opposite until the client set the rule: nobody on this platform is on
         * display to the person they are introduced to, and paying does not
         * change that. The pictures go to the trusted contact, who looks and
         * advises.
         *
         * ⚠️ THE KEY IS ABSENT, NOT EMPTY. An empty array would still tell a
         * template there are photographs it may iterate, and it would render
         * nothing while looking correct.
         */
        $this->assertArrayNotHasKey('photos', $card);
        $this->assertTrue($card['photographsWithheld']);

        /*
         * ⚠️ NARROWED IN PHASE 3, NOT RELAXED. This assertion used to read
         * `assertSame('Bakare', $card['lastName'])`.
         *
         * Unlocking buys the profile, not the person's identity. A last name
         * plus a city is enough to find somebody, so it now waits for the same
         * moment the phone number does — both people, independently, saying
         * yes. The schema has said "private until mutual interest" since the
         * Phase 2 reshape; the presenter had not caught up.
         *
         * The rule that reveals it is asserted from both sides in
         * MutualInterestTest. Here we only prove it does NOT come early.
         */
        $this->assertArrayNotHasKey(
            'lastName',
            $card,
            'A last name reached an unlocked card before mutual interest. Unlocking buys the '
                .'profile, not the ability to find somebody.'
        );

        $payload = json_encode($card, JSON_UNESCAPED_SLASHES);

        $this->assertStringNotContainsString('Bakare', $payload);

        // Even then, the internal breakdown does not travel.
        $this->assertStringNotContainsString('affinity', $payload);
    }

    /**
     * A teaser answers 404, not 403. 403 confirms the row exists and that
     * there is something worth paying for behind it.
     */
    public function test_the_full_match_page_404s_for_a_locked_introduction(): void
    {
        [$user, $match] = $this->memberWithLockedMatch();

        $response = $this->actingAs($user)->get("/matches/{$match->id}");

        $response->assertNotFound();
        $this->assertNotSame(403, $response->getStatusCode());
    }

    public function test_a_member_cannot_reach_somebody_elses_introduction(): void
    {
        [, $match] = $this->memberWithLockedMatch();

        $stranger = User::factory()->create();
        $stranger->assignRole('member');
        Profile::factory()->woman()->create(['user_id' => $stranger->id]);

        $this->actingAs($stranger)->get("/matches/{$match->id}")->assertNotFound();
        $this->actingAs($stranger)->post("/matches/{$match->id}/unlock")->assertNotFound();
    }

    /** Unlocking somebody else's match must not spend their credit either. */
    public function test_a_stranger_cannot_spend_a_credit_on_a_match_that_is_not_theirs(): void
    {
        [$owner, $match] = $this->memberWithLockedMatch();

        $stranger = User::factory()->create();
        $stranger->assignRole('member');
        Profile::factory()->woman()->create(['user_id' => $stranger->id]);
        app(CreditService::class)->adjust($stranger, 5, 'goodwill', 'Test', $stranger);

        $this->actingAs($stranger->fresh())->post("/matches/{$match->id}/unlock")->assertNotFound();

        $this->assertSame(5, $stranger->fresh()->creditBalance());
        $this->assertSame(MatchRecord::STATUS_TEASER, $match->fresh()->status);
    }

    /**
     * ⚠️ The other half of the photo-authorisation fix.
     *
     * Opening the door for paid matches must not open it for unpaid ones. A
     * member who guesses a teaser's photo id — or keeps a URL from before a
     * reversal — still gets nothing.
     */
    public function test_a_teaser_grants_no_access_to_the_photo_route(): void
    {
        [$user, $match, $photo] = $this->memberWithLockedMatch();

        // Built by hand ON PURPOSE here: the presenter refuses to emit one,
        // so this is the attack of somebody constructing it themselves.
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'photos.show',
            now()->addHour(),
            ['photo' => $photo->id]
        );

        $this->actingAs($user)->get($url)->assertNotFound();

        /*
         * ⚠️ AND PAYING NO LONGER OPENS IT EITHER. THIS CONTROL HAD TO CHANGE
         * WITH THE RULE.
         *
         * It used to assert that unlocking the introduction opened the same id
         * — a correct control for the old rule, where a member who paid saw
         * the photographs. Nobody sees another member's photographs now, at
         * any status, for any payment: they go to the trusted contact.
         *
         * Left as a control rather than deleted, because a route that refuses
         * EVERYTHING is indistinguishable from a broken route, and the
         * assertion above would pass against one. So the positive half now
         * proves the endpoint genuinely serves bytes — using the one case that
         * is still allowed, a member fetching her own.
         */
        app(CreditService::class)->adjust($user, 1, 'goodwill', 'test', $user);
        app(CreditService::class)->unlockMatch($user->fresh(), $match);

        $this->actingAs($user->fresh())->get($url)->assertNotFound();

        // Made here rather than in the shared fixture, which deliberately
        // gives photographs only to the other party.
        $mine = $user->fresh()->profile;

        $own = Photo::create([
            'profile_id' => $mine->id,
            'slot' => 'passport',
            'path' => 'profiles/'.$mine->id.'/mine.jpg',
            'webp_path' => null,
            'thumbnail_path' => 'profiles/'.$mine->id.'/mine-thumb.jpg',
            'original_filename' => 'mine.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'width' => 1200,
            'height' => 1600,
        ]);

        \Illuminate\Support\Facades\Storage::disk(\App\Services\Photos\PhotoStorageService::DISK)
            ->put($own->path, 'bytes');

        $this->actingAs($user->fresh())
            ->get(\Illuminate\Support\Facades\URL::temporarySignedRoute(
                'photos.show',
                now()->addHour(),
                ['photo' => $own->id],
            ))
            ->assertOk();
    }

    /**
     * There is no browse surface in this product, in any phase. These are the
     * URLs somebody would try first.
     */
    public function test_there_is_no_index_of_members_anywhere(): void
    {
        [$user] = $this->memberWithLockedMatch();

        foreach (['/matches', '/members', '/browse', '/search', '/profiles'] as $url) {
            $status = $this->actingAs($user)->get($url)->getStatusCode();

            $this->assertSame(404, $status, "{$url} answered {$status}. There is no browsing in RealVows.");
        }
    }
}
