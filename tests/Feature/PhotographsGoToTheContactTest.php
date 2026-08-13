<?php

namespace Tests\Feature;

use App\Models\MatchRecord;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\User;
use App\Support\MatchPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Nobody sees the photographs of the person they are introduced to.
 *
 * ⚠️ THIS IS THE CLIENT'S RULE AND IT IS A RELIGIOUS ONE. Nobody on this
 * platform is on display. The pictures exist so a member can be verified as
 * real, and so the person responsible for them can look properly — not so two
 * strangers can appraise each other. They go to the trusted contact.
 *
 * ⚠️ ASKED AT THE EMISSION POINTS, NOT OF THE TEMPLATES. There are two doors
 * and both have to be shut:
 *
 *   · MatchPresenter, which decides what JSON a member receives
 *   · PhotoController, which decides who gets the bytes
 *
 * Removing the URL from the payload alone would not be a rule. A link issued
 * yesterday still verifies today, a photo id is a small integer somebody can
 * type, and a devtools screenshot outlives any template change.
 *
 * Every case is a pair. A presenter that returned nothing and a route that
 * refused everybody would satisfy half of this file while breaking the product.
 */
class PhotographsGoToTheContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        Storage::fake('private');
    }

    /** @return array{0: User, 1: MatchRecord, 2: Photo} */
    private function unlockedIntroduction(): array
    {
        $me = User::factory()->create();
        $me->assignRole('member');
        $mine = Profile::factory()->create(['user_id' => $me->id, 'gender' => 'woman']);

        $them = Profile::factory()->create(['user_id' => User::factory()->create()->id, 'gender' => 'man']);

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

        Storage::disk('private')->put($photo->path, 'bytes');

        $match = MatchRecord::create([
            'seeker_profile_id' => $mine->id,
            'matched_profile_id' => $them->id,
            'total_score' => 88,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
        ]);

        return [$me->fresh(), $match, $photo];
    }

    public function test_no_member_payload_carries_a_photograph(): void
    {
        [, $match] = $this->unlockedIntroduction();

        $presenter = app(MatchPresenter::class);
        $checked = 0;

        foreach (['teaser', 'full', 'forCard'] as $method) {
            $payload = $presenter->{$method}($match->fresh());

            $this->assertArrayNotHasKey('photos', $payload, "{$method}() emitted a photos key.");

            /*
             * ⚠️ SEARCHED IN THE ENCODED PAYLOAD, not only by key. A URL could
             * arrive nested inside `sections`, `commonalities` or anything else
             * added later, and a key check would never see it.
             */
            $this->assertStringNotContainsString(
                '/photos/',
                json_encode($payload, JSON_UNESCAPED_SLASHES),
                "{$method}() emitted a photo route somewhere in its payload."
            );

            $checked++;
        }

        // Prove the loop ran over every method rather than none.
        $this->assertSame(3, $checked);
    }

    public function test_an_opened_introduction_says_why_rather_than_going_quiet(): void
    {
        [, $match] = $this->unlockedIntroduction();

        $payload = app(MatchPresenter::class)->full($match->fresh());

        /*
         * A card with no picture and no comment on it reads as a card that
         * failed to load one. The flag is what lets the interface say so.
         */
        $this->assertTrue($payload['photographsWithheld']);
    }

    public function test_the_bytes_are_refused_even_though_the_introduction_is_unlocked(): void
    {
        [$me, , $photo] = $this->unlockedIntroduction();

        /*
         * ⚠️ THE URL IS BUILT BY HAND ON PURPOSE. The presenter no longer
         * issues one, so this is the case that actually matters: somebody who
         * kept an old link, or typed an id. If only the payload had changed,
         * this would still serve the file.
         */
        $url = URL::temporarySignedRoute('photos.show', now()->addHour(), ['photo' => $photo->id]);

        $this->actingAs($me)->get($url)->assertNotFound();
    }

    public function test_a_member_can_still_fetch_her_own(): void
    {
        /*
         * ⚠️ THE CONTROL, AND THE WHOLE FILE DEPENDS ON IT. A route that
         * refused everybody would pass every assertion above while making it
         * impossible for anybody to manage their own profile.
         */
        [$me] = $this->unlockedIntroduction();

        $mine = $me->profile;

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

        Storage::disk('private')->put($own->path, 'bytes');

        $this->actingAs($me)
            ->get(URL::temporarySignedRoute('photos.show', now()->addHour(), ['photo' => $own->id]))
            ->assertOk();
    }

    public function test_a_verified_family_contact_still_receives_them(): void
    {
        /*
         * ⚠️ THE OTHER CONTROL. If photographs reached nobody at all, the rule
         * would not be "the trusted contact looks and advises" — it would be
         * "nobody looks", and the guardian role would be pointless. This is the
         * whole reason the withholding is acceptable.
         */
        [$me, $match] = $this->unlockedIntroduction();

        $contact = User::factory()->create(['email' => 'contact@realvows.test']);

        \App\Models\Guardian::create([
            'member_id' => $me->id,
            'user_id' => $contact->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => \App\Models\Guardian::STATUS_VERIFIED,
            'is_appointed' => false,
            'verified_at' => now(),
        ]);

        $props = $this->actingAs($contact)->get('/trusted-contact-dashboard')->viewData('page')['props'];

        $this->assertNotEmpty($props['introductions'], 'The contact was shown no introduction to advise on.');
        $this->assertArrayHasKey('photos', $props['introductions'][0]);
        $this->assertSame($match->matched_profile_id, $props['introductions'][0]['profileId'] ?? $match->matched_profile_id);
    }
}
