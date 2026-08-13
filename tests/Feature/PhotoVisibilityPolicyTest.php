<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\GuardianDecision;
use App\Models\MatchRecord;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\User;
use App\Services\Photos\PhotoStorageService;
use App\Services\Settings\SettingsService;
use App\Support\MatchPresenter;
use App\Support\PhotoVisibilityPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * ⚠️ WHETHER A MEMBER EVER SEES A PHOTOGRAPH IS A SETTING, AND IT HAS TO
 * ACTUALLY MOVE.
 *
 * A dropdown that does not change behaviour is worse than a hardcoded rule: the
 * rule is at least honest about what it is. The client would set it, see the
 * option saved, and believe the platform had changed.
 *
 * So every level is exercised at BOTH emission points — the payload
 * MatchPresenter builds, and the bytes PhotoController serves — because
 * withholding a URL is not a rule while the route that serves it still answers,
 * and a signed link issued yesterday still verifies today.
 *
 * ⚠️ AND THE DEFAULT IS ASSERTED SEPARATELY. `never` is the shipped behaviour
 * and the recoverable direction; if a future edit makes the permissive reading
 * the fallback, a photograph is shown that cannot be recalled.
 */
class PhotoVisibilityPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        Storage::fake(PhotoStorageService::DISK);
    }

    private function level(string $level): void
    {
        app(SettingsService::class)->set(PhotoVisibilityPolicy::GROUP, PhotoVisibilityPolicy::KEY, $level);
        app(SettingsService::class)->flush();
    }

    /** @return array{0: User, 1: MatchRecord, 2: Photo, 3: Guardian} */
    private function introduction(): array
    {
        $me = User::factory()->create(['email_verified_at' => now()]);
        $me->assignRole('member');
        $mine = Profile::factory()->woman()->create(['user_id' => $me->id, 'submitted_at' => now()]);

        $them = Profile::factory()->man()->create(['submitted_at' => now()]);

        $photo = Photo::create([
            'profile_id' => $them->id,
            'slot' => 'passport',
            'path' => 'profiles/'.$them->id.'/p.jpg',
            'webp_path' => null,
            'thumbnail_path' => 'profiles/'.$them->id.'/p-thumb.jpg',
            'original_filename' => 'p.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'width' => 1200,
            'height' => 1600,
        ]);
        Storage::disk(PhotoStorageService::DISK)->put($photo->path, 'bytes');

        $match = MatchRecord::create([
            'seeker_profile_id' => $mine->id,
            'matched_profile_id' => $them->id,
            'total_score' => 88,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
        ]);

        $guardian = Guardian::create([
            'member_id' => $me->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => Guardian::STATUS_VERIFIED,
            'is_appointed' => false,
            'verified_at' => now(),
        ]);

        return [$me->fresh(), $match->fresh(), $photo, $guardian];
    }

    private function approve(Guardian $guardian, MatchRecord $match, string $gate): void
    {
        GuardianDecision::create([
            'guardian_id' => $guardian->id,
            'match_id' => $match->id,
            'gate' => $gate,
            'decision' => 'approved',
            'decided_at' => now(),
        ]);
    }

    private function bytes(User $me, Photo $photo): int
    {
        return $this->actingAs($me)
            ->get(URL::temporarySignedRoute('photos.show', now()->addHour(), ['photo' => $photo->id]))
            ->getStatusCode();
    }

    // ── never ────────────────────────────────────────────────────────────

    public function test_the_shipped_default_is_never(): void
    {
        /*
         * ⚠️ READ FROM A FRESHLY SEEDED DATABASE, not from the constant. The
         * constant saying 'never' proves nothing about what a new install
         * actually boots with.
         */
        $this->assertSame(PhotoVisibilityPolicy::NEVER, app(PhotoVisibilityPolicy::class)->level());
    }

    public function test_never_withholds_from_the_payload_and_refuses_the_bytes(): void
    {
        $this->level(PhotoVisibilityPolicy::NEVER);

        [$me, $match, $photo, $guardian] = $this->introduction();

        // Even with every approval in place, `never` means never.
        $this->approve($guardian, $match, 'introduction');

        $payload = app(MatchPresenter::class)->full($match->fresh());

        $this->assertArrayNotHasKey('photos', $payload);
        $this->assertTrue($payload['photographsWithheld']);

        $this->assertSame(404, $this->bytes($me, $photo));
    }

    // ── after_contact_approval ───────────────────────────────────────────

    public function test_after_contact_approval_withholds_until_the_contact_approves(): void
    {
        $this->level(PhotoVisibilityPolicy::AFTER_CONTACT_APPROVAL);

        [$me, $match, $photo] = $this->introduction();

        $payload = app(MatchPresenter::class)->full($match->fresh());

        $this->assertArrayNotHasKey('photos', $payload, 'Photographs arrived before the trusted contact had approved anything.');
        $this->assertSame(404, $this->bytes($me, $photo));
    }

    public function test_after_contact_approval_releases_once_he_has(): void
    {
        /*
         * ⚠️ THE POSITIVE CONTROL FOR THIS LEVEL. Without it, the refusal above
         * would pass just as well against a level that never releases anything
         * — which is `never` wearing a different name, and the client would
         * have set a dropdown that does nothing.
         */
        $this->level(PhotoVisibilityPolicy::AFTER_CONTACT_APPROVAL);

        [$me, $match, $photo, $guardian] = $this->introduction();

        $this->approve($guardian, $match, 'introduction');

        $payload = app(MatchPresenter::class)->full($match->fresh());

        $this->assertArrayNotHasKey('photographsWithheld', $payload);
        $this->assertNotEmpty($payload['photos'], 'The photos key is present but empty.');

        $this->assertSame(200, $this->bytes($me, $photo));
    }

    // ── at_mutual_interest ───────────────────────────────────────────────

    public function test_at_mutual_interest_withholds_until_both_have_said_yes(): void
    {
        $this->level(PhotoVisibilityPolicy::AT_MUTUAL_INTEREST);

        [$me, $match, $photo, $guardian] = $this->introduction();

        // One side only, plus a trusted-contact approval that is not this rule.
        $this->approve($guardian, $match, 'introduction');
        $match->forceFill(['response' => MatchRecord::RESPONSE_INTERESTED])->save();

        $payload = app(MatchPresenter::class)->full($match->fresh());

        $this->assertArrayNotHasKey('photos', $payload, 'One person saying yes released the photographs.');
        $this->assertSame(404, $this->bytes($me, $photo));
    }

    public function test_at_mutual_interest_releases_when_both_have(): void
    {
        $this->level(PhotoVisibilityPolicy::AT_MUTUAL_INTEREST);

        [$me, $match, $photo] = $this->introduction();

        $match->forceFill(['response' => MatchRecord::RESPONSE_INTERESTED])->save();

        // The reciprocal row is what makes it mutual — there is no flag to set.
        MatchRecord::create([
            'seeker_profile_id' => $match->matched_profile_id,
            'matched_profile_id' => $match->seeker_profile_id,
            'total_score' => 88,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
            'response' => MatchRecord::RESPONSE_INTERESTED,
        ]);

        $payload = app(MatchPresenter::class)->full($match->fresh());

        $this->assertNotEmpty($payload['photos'] ?? [], 'Both sides said yes and no photographs arrived.');
        $this->assertSame(200, $this->bytes($me, $photo));
    }

    // ── the failure modes ────────────────────────────────────────────────

    public function test_an_unrecognised_value_reads_as_never(): void
    {
        /*
         * A typo is not a third meaning. The strict reading is the recoverable
         * one: a photograph withheld in error is a page that says less than it
         * should, and a photograph shown in error is on somebody's screen.
         */
        $this->level('after_mutual_whatever');

        $this->assertSame(PhotoVisibilityPolicy::NEVER, app(PhotoVisibilityPolicy::class)->level());
    }

    public function test_a_member_always_sees_their_own_regardless_of_the_level(): void
    {
        /*
         * ⚠️ THE CONTROL THE WHOLE FILE RESTS ON. Every refusal above is a 404,
         * and a route that refused everybody would produce all of them while
         * making it impossible for anyone to manage their own profile.
         */
        $this->level(PhotoVisibilityPolicy::NEVER);

        [$me] = $this->introduction();

        $own = Photo::create([
            'profile_id' => $me->profile->id,
            'slot' => 'passport',
            'path' => 'profiles/'.$me->profile->id.'/mine.jpg',
            'webp_path' => null,
            'thumbnail_path' => 'profiles/'.$me->profile->id.'/mine-thumb.jpg',
            'original_filename' => 'mine.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'width' => 1200,
            'height' => 1600,
        ]);
        Storage::disk(PhotoStorageService::DISK)->put($own->path, 'bytes');

        $this->assertSame(200, $this->bytes($me, $own));
    }

    public function test_a_stranger_is_refused_at_every_level(): void
    {
        /*
         * ⚠️ THE LEVELS GOVERN AN INTRODUCTION, NOT THE PLATFORM. Somebody with
         * no match row between them has no claim at any setting, and the most
         * permissive level must not become a directory.
         */
        [, , $photo] = $this->introduction();

        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $stranger->assignRole('member');
        Profile::factory()->create(['user_id' => $stranger->id]);

        foreach (PhotoVisibilityPolicy::levels() as $level) {
            $this->level($level);

            $this->assertSame(
                404,
                $this->bytes($stranger->fresh(), $photo),
                "A member with no introduction to this profile was served a photograph at `{$level}`.",
            );
        }
    }

    public function test_the_reason_matches_the_level(): void
    {
        // A withheld card with no comment on it reads as one that failed to
        // load, and the member reports a bug that is not there.
        $seen = [];

        foreach (PhotoVisibilityPolicy::levels() as $level) {
            $this->level($level);
            $seen[] = app(PhotoVisibilityPolicy::class)->explanation();
        }

        $this->assertCount(3, array_unique($seen), 'Two levels give the member the same explanation.');

        foreach ($seen as $sentence) {
            $this->assertNotEmpty(trim($sentence));
        }
    }
}
