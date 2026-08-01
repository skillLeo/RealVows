<?php

namespace Tests\Feature;

use App\Models\MatchRecord;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ⚠️ RULE 1: A TEST MUST CONSUME THE ARTEFACT THE SYSTEM PRODUCED, NEVER A
 * RECONSTRUCTION OF IT.
 *
 * This file exists because of a live outage. Checkout was dead on the
 * production site while the whole suite was green — every callback test
 * built the signed URL itself with `URL::signedRoute(...)` and posted to
 * that, so the URL the gateway actually constructed was never exercised. The
 * gateway was appending to a signed URL, and a signature covers the entire
 * query string.
 *
 * The tests were checking a reconstruction. The reconstruction was correct.
 * The real thing was not.
 *
 * So: every signed URL, verification link, reset link and photo URL below is
 * EXTRACTED from what the system emitted — a mailable's rendered body, a
 * notification object, a serialiser's output — and then followed. Nothing
 * here builds a URL.
 *
 * This matters more in Phase 3 than it does now, because Phase 3 is almost
 * entirely links inside emails and WhatsApp messages.
 */
class SignedArtefactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    /** Pull every http(s) URL out of a rendered body. */
    private function urlsIn(string $html): array
    {
        preg_match_all('/https?:\/\/[^\s"\'<>\)]+/', html_entity_decode($html), $m);

        return array_values(array_unique($m[0]));
    }

    // ── Email verification ───────────────────────────────────────────────

    /**
     * The link a new member actually receives, followed as they would follow
     * it — extracted from the notification the system built.
     */
    public function test_the_verification_link_the_member_receives_actually_verifies_them(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $user->assignRole('member');

        $user->sendEmailVerificationNotification();

        $captured = null;

        Notification::assertSentTo($user, \App\Notifications\VerifyEmailAddress::class, function ($notification) use ($user, &$captured) {
            // The rendered mail, exactly as it would be sent.
            $captured = $notification->toMail($user);

            return true;
        });

        $this->assertNotNull($captured, 'No verification notification was produced.');

        $urls = $this->urlsIn($captured->render());
        $verifyUrl = collect($urls)->first(fn ($u) => str_contains($u, '/verify-email/'));

        $this->assertNotNull(
            $verifyUrl,
            'The verification email contains no verify link. URLs found: '.implode(', ', $urls)
        );

        // ⚠️ Followed as-is. Not rebuilt.
        $this->actingAs($user)->get($verifyUrl)->assertRedirect();

        $this->assertTrue($user->fresh()->hasVerifiedEmail(), 'The emailed link did not verify the member.');
    }

    public function test_a_tampered_verification_link_is_refused(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();

        $captured = null;
        Notification::assertSentTo($user, \App\Notifications\VerifyEmailAddress::class, function ($n) use ($user, &$captured) {
            $captured = $n->toMail($user);

            return true;
        });

        $url = collect($this->urlsIn($captured->render()))->first(fn ($u) => str_contains($u, '/verify-email/'));

        // One character of the signature changed.
        $tampered = preg_replace('/signature=(.)/', 'signature='.(str_contains($url, 'signature=a') ? 'b' : 'a'), $url);

        $this->actingAs($user)->get($tampered)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    // ── Password reset ───────────────────────────────────────────────────

    public function test_the_reset_link_the_member_receives_actually_opens_the_reset_form(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        $captured = null;

        Notification::assertSentTo($user, \App\Notifications\ResetPasswordLink::class, function ($notification) use ($user, &$captured) {
            $captured = $notification->toMail($user);

            return true;
        });

        $this->assertNotNull($captured, 'No reset notification was produced.');

        $url = collect($this->urlsIn($captured->render()))
            ->first(fn ($u) => str_contains($u, '/reset-password'));

        $this->assertNotNull($url, 'The reset email contains no reset link.');

        // Followed as emitted, query string and all.
        $this->get($url)->assertOk();
    }

    // ── Photo URLs ───────────────────────────────────────────────────────

    /**
     * The signed photo URL is generated by MatchPresenter. This follows THAT
     * string rather than calling `temporarySignedRoute` again — the two could
     * diverge, and only one of them is what the browser gets.
     */
    public function test_the_photo_url_the_serialiser_emitted_actually_serves_the_photo(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('profiles/seed/p.jpg', 'not-a-real-jpeg-but-a-real-file');

        [$user, $match] = $this->unlockedMatchWithPhoto();

        $card = $this->actingAs($user)->get('/dashboard')->viewData('page')['props']['matches'][0];

        $this->assertNotEmpty($card['photos'], 'The unlocked card carried no photos.');

        $url = $card['photos'][0]['url'];

        // ⚠️ The exact emitted string, followed.
        $response = $this->actingAs($user)->get($url);

        $this->assertNotSame(403, $response->getStatusCode(), 'The emitted photo URL failed its own signature check.');
        $this->assertNotSame(404, $response->getStatusCode(), 'The emitted photo URL points at nothing.');
    }

    public function test_the_emitted_photo_url_does_not_work_for_a_different_member(): void
    {
        Storage::fake('private');

        [$user, $match] = $this->unlockedMatchWithPhoto();

        $url = $this->actingAs($user)->get('/dashboard')
            ->viewData('page')['props']['matches'][0]['photos'][0]['url'];

        $stranger = User::factory()->create();
        $stranger->assignRole('member');
        Profile::factory()->woman()->create(['user_id' => $stranger->id]);

        $status = $this->actingAs($stranger->fresh())->get($url)->getStatusCode();

        $this->assertContains(
            $status,
            [403, 404],
            "A signed photo URL emitted for one member answered {$status} for another."
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL for the extractor. If `urlsIn()` silently stopped
     * matching, every test above would pass having followed nothing.
     */
    public function test_the_url_extractor_actually_finds_urls(): void
    {
        $found = $this->urlsIn(
            '<a href="https://realvows.test/verify-email/1/abc?expires=1&amp;signature=deadbeef">Confirm</a>'
        );

        $this->assertSame(
            ['https://realvows.test/verify-email/1/abc?expires=1&signature=deadbeef'],
            $found,
            'The extractor cannot find a URL, so every test in this file follows nothing.'
        );
    }

    /**
     * @return array{0: User, 1: MatchRecord}
     */
    private function unlockedMatchWithPhoto(): array
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $mine = Profile::factory()->woman()->create(['user_id' => $user->id]);
        $them = Profile::factory()->man()->create();

        Photo::create([
            'profile_id' => $them->id,
            'slot' => 'passport',
            'path' => 'profiles/seed/p.jpg',
            'webp_path' => 'profiles/'.$them->id.'/p.webp',
            'thumbnail_path' => 'profiles/'.$them->id.'/p-t.jpg',
            'original_filename' => 'p.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'width' => 800,
            'height' => 1000,
        ]);

        $match = MatchRecord::create([
            'seeker_profile_id' => $mine->id,
            'matched_profile_id' => $them->id,
            'total_score' => 90,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
        ]);

        return [$user->fresh(), $match];
    }
}
