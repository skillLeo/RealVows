<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\Profile;
use App\Models\ProfilePreference;
use App\Models\User;
use App\Models\Verification;
use App\Services\Photos\PhotoStorageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ⚠️ EMAIL VERIFICATION MOVED FROM THE DOOR OF THE PRODUCT TO THE DOOR OF THE
 * POOL, AND THAT IS A BIGGER CHANGE THAN IT SOUNDS.
 *
 * ── Why it moved ──────────────────────────────────────────────────────────
 *
 * The client: "if someone has to go on their email first to confirm their
 * email, then they might not bother with their profile." He is right. Somebody
 * who has typed a password and nothing else has invested nothing, and sending
 * them to an inbox at that exact moment is where they stop.
 *
 * ── ⚠️ WHAT THAT BROKE, AND IT WOULD HAVE BROKEN SILENTLY ────────────────
 *
 * `Profile::scopeMatchable` checks IDENTITY verification — the reviewed
 * photograph and code. It has never known anything about email. It did not
 * need to: the `verified` middleware meant an unverified member could not
 * reach the profile builder at all, so they could never become matchable.
 *
 * The moment that middleware came off, an unverified member could complete a
 * profile, be approved by a reviewer, and enter the pool. Nothing in the
 * codebase would have objected, because the guarantee had never lived in the
 * query — it lived in a middleware two layers away that nobody thought of as
 * part of matching.
 *
 * ⚠️ SO THE EXCLUSION IS IN THE QUERY. An unverified member is ABSENT from the
 * candidate set, not filtered out of its results. Same rule as the
 * trusted-contact gate, same reason: a filter is one forgotten call site away
 * from a leak, and a row that was never selected cannot leak at all.
 */
class UnverifiedCanBuildButNotBeMatchedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        Storage::fake(PhotoStorageService::DISK);
    }

    /**
     * A member who has done everything a member can do, and who differs from a
     * finished member in exactly one respect.
     */
    private function member(bool $emailVerified): User
    {
        $user = User::factory()->create([
            'email_verified_at' => $emailVerified ? now() : null,
        ]);
        $user->assignRole('member');

        $profile = Profile::factory()->woman()->create([
            'user_id' => $user->id,
            'submitted_at' => now(),
            'relationship_goal' => 'marriage_soon',
            'bio' => str_repeat('a considered sentence about myself and my hopes ', 30),
        ]);

        ProfilePreference::factory()->create(['profile_id' => $profile->id]);

        foreach (['passport', 'full_length', 'social', 'open'] as $slot) {
            Photo::create([
                'profile_id' => $profile->id,
                'slot' => $slot,
                'path' => "profiles/{$profile->id}/{$slot}.jpg",
                'webp_path' => null,
                'thumbnail_path' => "profiles/{$profile->id}/{$slot}-thumb.jpg",
                'original_filename' => "{$slot}.jpg",
                'mime' => 'image/jpeg',
                'size_bytes' => 1024,
                'width' => 1200,
                'height' => 1600,
            ]);
        }

        /*
         * ⚠️ IDENTITY VERIFIED IN BOTH CASES, WHICH IS THE ENTIRE POINT. If the
         * unverified fixture were also unreviewed, its absence from the pool
         * would prove nothing about email — the old check would already have
         * excluded it and this file would be testing the wrong thing.
         */
        Verification::create([
            'profile_id' => $profile->id,
            'status' => Verification::STATUS_VERIFIED,
            'code' => 'AB12CD',
            'verified_at' => now(),
        ]);

        return $user->fresh();
    }

    // ── They can build ───────────────────────────────────────────────────

    public function test_an_unverified_member_can_build_their_whole_profile(): void
    {
        $user = $this->member(emailVerified: false);

        // ⚠️ /profile always redirects to the step they stopped on. That is
        // resumption, not a gate — asserting 200 here would be asserting the
        // wrong thing and would fail for a reason unrelated to verification.
        $this->actingAs($user)->get('/profile')->assertRedirect();
        $this->actingAs($user)->get('/profile/values')->assertOk();
        $this->actingAs($user)->putJson('/profile/values', ['bio' => 'A sentence.'])->assertOk();
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    // ── They cannot be matched ───────────────────────────────────────────

    public function test_an_unverified_member_is_absent_from_the_candidate_query(): void
    {
        $unverified = $this->member(emailVerified: false);

        $matchable = Profile::query()->matchable()->pluck('user_id')->all();

        $this->assertNotContains(
            $unverified->id,
            $matchable,
            'A member who has not confirmed their email address is in the matching pool. '
                .'Email verification is the gate on the pool now that it is no longer the gate '
                .'on the product, and it has to live in the query.',
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL, AND WITHOUT IT THE ASSERTION ABOVE IS WORTHLESS.
     *
     * An empty candidate set satisfies "the unverified member is not in it"
     * perfectly, and an empty pool is exactly what a broken `matchable` scope
     * produces. This proves the same fixture, differing only in the email
     * timestamp, IS selected.
     */
    public function test_the_same_member_with_a_confirmed_address_is_present(): void
    {
        $verified = $this->member(emailVerified: true);

        $matchable = Profile::query()->matchable()->pluck('user_id')->all();

        $this->assertContains(
            $verified->id,
            $matchable,
            'A fully finished, identity-verified member with a confirmed address is missing '
                .'from the pool, so the exclusion above is excluding everybody.',
        );
    }

    // ── The review queue waits too ───────────────────────────────────────

    public function test_submitting_unverified_does_not_open_the_review_queue(): void
    {
        /*
         * A reviewer's time is the scarcest thing here and every row is a real
         * person opening real photographs. Somebody who typed an address they
         * cannot receive mail at is not a member yet.
         */
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => null]);
        $user->assignRole('member');

        $profile = Profile::factory()->woman()->create([
            'user_id' => $user->id,
            'submitted_at' => null,
            'relationship_goal' => 'marriage_soon',
            'bio' => str_repeat('a considered sentence about myself and my hopes ', 30),
        ]);
        ProfilePreference::factory()->create(['profile_id' => $profile->id]);

        foreach (['passport', 'full_length', 'social', 'open'] as $slot) {
            Photo::create([
                'profile_id' => $profile->id, 'slot' => $slot,
                'path' => "profiles/{$profile->id}/{$slot}.jpg",
                'webp_path' => null, 'thumbnail_path' => null,
                'original_filename' => "{$slot}.jpg", 'mime' => 'image/jpeg',
                'size_bytes' => 1024, 'width' => 1200, 'height' => 1600,
            ]);
        }

        Verification::create([
            'profile_id' => $profile->id,
            'status' => Verification::STATUS_REVIEW,
            'code' => 'AB12CD',
        ]);

        $this->actingAs($user)->post('/profile/submit');

        $this->assertNotSame(
            Verification::STATUS_PENDING,
            $profile->fresh()->verification->status,
            'An unverified member reached the review queue. A reviewer would spend real time '
                .'on somebody who may never confirm an address at all.',
        );

        // ⚠️ Their work is still saved. This holds the queue, not the member.
        $this->assertNotNull($profile->fresh()->submitted_at);
    }

    // ── And nobody else is written to on their word alone ────────────────

    /**
     * ⚠️ NAMING A TRUSTED CONTACT NOW REACHES A THIRD PERSON WHO NEVER SIGNED
     * UP, ON THE SAY-SO OF SOMEBODY WE HAVE NOT CONFIRMED IS CONTACTABLE.
     *
     * This gap did not exist before. The builder sat behind the `verified`
     * middleware, so anybody who got as far as this screen had already proved
     * their address. Opening the builder opened this with it, and the person
     * who pays for a mistake here is not the member — it is her father,
     * receiving mail about a daughter from a platform he has never heard of.
     */
    public function test_naming_a_contact_before_confirming_writes_to_nobody(): void
    {
        Mail::fake();

        $user = $this->member(emailVerified: false);

        $this->actingAs($user)->post('/trusted-contact', [
            'name' => 'Yusuf Rahman',
            'relationship' => 'father',
            'email' => 'yusuf@gmail.com',
            'phone' => '+18761234567',
        ]);

        /*
         * ⚠️ BOTH, BECAUSE QUEUED MAIL IS NEVER "SENT". A message pushed onto
         * the queue satisfies assertNothingSent perfectly while still going out
         * a second later, and the person it reaches is the one who never signed
         * up. AssertionsCanFailTest enforces the pairing across the suite.
         */
        Mail::assertNothingSent();
        Mail::assertNothingQueued();

        /*
         * ⚠️ HER WORK IS STILL SAVED, AND THAT IS THE DIFFERENCE BETWEEN
         * HOLDING A MESSAGE AND REJECTING A FORM. Making her type it again
         * after confirming would read as the platform losing her answer.
         */
        $this->assertDatabaseHas('guardians', [
            'member_id' => $user->id,
            'name' => 'Yusuf Rahman',
        ]);
    }

    /**
     * ⚠️ POSITIVE CONTROL. `assertNothingSent` is satisfied just as neatly by a
     * broken invitation that never sends to anyone, which would strand every
     * trusted contact on the platform in silence.
     */
    public function test_the_same_nomination_from_a_confirmed_member_goes_out(): void
    {
        Mail::fake();

        $user = $this->member(emailVerified: true);

        $this->actingAs($user)->post('/trusted-contact', [
            'name' => 'Yusuf Rahman',
            'relationship' => 'father',
            'email' => 'yusuf@gmail.com',
            'phone' => '+18761234567',
        ]);

        Mail::assertSent(\App\Mail\TrustedContactInvitation::class);
    }

    /**
     * ⚠️ THE HOLD IS DEFEATED ONE MORNING LATER IF THE REMINDER DOES NOT KNOW
     * ABOUT IT, AND THE REMINDER HAS NO REASON TO KNOW.
     *
     * GuardianService writes `status => invited` when a member names somebody.
     * That was true the instant it was written until R5, because naming a
     * contact required a confirmed account and the message left in the same
     * request. RemindTrustedContacts was entitled to read `invited` as "we have
     * written to this person" and it did.
     *
     * Holding the invitation broke that invariant without touching the file
     * that depends on it. The father would have received the daily chase for an
     * introduction he was never told about.
     */
    public function test_the_daily_reminder_does_not_chase_a_held_invitation(): void
    {
        Mail::fake();

        $user = $this->member(emailVerified: false);

        $this->actingAs($user)->post('/trusted-contact', [
            'name' => 'Yusuf Rahman',
            'relationship' => 'father',
            'email' => 'yusuf@gmail.com',
            'phone' => '+18761234567',
        ]);

        // ⚠️ The row really does say `invited`, which is the whole trap. If this
        // fixture stopped being written that way the test below would pass for
        // a reason that has nothing to do with the reminder.
        $this->assertDatabaseHas('guardians', [
            'member_id' => $user->id,
            'status' => \App\Models\Guardian::STATUS_INVITED,
        ]);

        $this->travel(8)->days();
        $this->artisan('realvows:remind-trusted-contacts')->assertSuccessful();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    /**
     * ⚠️ POSITIVE CONTROL. A reminder that had simply stopped working would
     * satisfy the assertion above perfectly, and every trusted contact on the
     * platform would quietly stop being chased.
     */
    public function test_the_reminder_still_chases_a_contact_who_was_really_invited(): void
    {
        Mail::fake();

        $user = $this->member(emailVerified: true);

        $this->actingAs($user)->post('/trusted-contact', [
            'name' => 'Yusuf Rahman',
            'relationship' => 'father',
            'email' => 'yusuf@gmail.com',
            'phone' => '+18761234567',
        ]);

        $this->travel(8)->days();
        $this->artisan('realvows:remind-trusted-contacts')->assertSuccessful();

        $any = fn (\Illuminate\Mail\Mailable $m) => true;
        $chased = Mail::sent($any)->count() + Mail::queued($any)->count();

        $this->assertGreaterThan(
            0,
            $chased,
            'The daily reminder chased nobody at all, so the assertion above is measuring a '
                .'broken command rather than a respected hold.',
        );
    }

    public function test_the_verification_email_is_sent_on_completion(): void
    {
        /*
         * ⚠️ THE OTHER HALF. Holding the queue while never asking them to
         * confirm would strand somebody permanently, having done all the work.
         */
        Notification::fake();

        $user = $this->member(emailVerified: false);
        $user->profile->forceFill(['submitted_at' => null])->save();

        $this->actingAs($user)->post('/profile/submit');

        /*
         * ⚠️ THE BRANDED NOTIFICATION, NOT LARAVEL'S DEFAULT. User overrides
         * sendEmailVerificationNotification to send VerifyEmailAddress, so
         * asserting the framework class would fail while the real one went out
         * — a test failing on a product that works.
         */
        Notification::assertSentTo($user, \App\Notifications\VerifyEmailAddress::class);
    }
}
