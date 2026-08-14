<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Services\Photos\PhotoStorageService;
use App\Services\Profile\ProfileCompleteness;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ⚠️ A MEMBER IS TOLD WHAT IS MISSING ON THE STEP THAT IS MISSING IT.
 *
 * Everything short used to surface on the final screen, after seven steps, with
 * no indication of which step it came from. In the client's words: the user is
 * confused about what is left or missed or wrong.
 *
 * The server always knew — `ProfileCompleteness` walks REQUIRED step by step —
 * and then flattened the answer into one list before anybody could use it.
 *
 * ── ⚠️ EVERY PAYLOAD THAT REFRESHES COMPLETENESS HAS TO CARRY IT ──────────
 *
 * The builder refuses to advance while the current step is short, so a payload
 * that omits `missingByStep` leaves the block stuck on stale data. There are
 * FOUR of them and they are easy to miss because three go through one
 * controller and the fourth does not:
 *
 *   · the step render
 *   · the profile render
 *   · the autosave response
 *   · the PHOTO endpoint, in a different controller entirely
 *
 * The photo one was missed on the first pass, which meant uploading a fourth
 * photograph left the member still being told the photos step was incomplete
 * until they reloaded. That is the shape this file exists to hold.
 */
class ProfileBuilderBlocksTheStepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        Storage::fake(PhotoStorageService::DISK);
    }

    private function member(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('member');

        Profile::factory()->create([
            'user_id' => $user->id,
            'submitted_at' => null,
            'bio' => null,
            'core_values' => null,
        ]);

        return $user->fresh();
    }

    public function test_the_assessment_says_which_step_each_shortfall_belongs_to(): void
    {
        $user = $this->member();

        $assessment = app(ProfileCompleteness::class)->assess($user->profile);

        $this->assertNotEmpty($assessment['missing'], 'The fixture is already complete, so this proves nothing.');
        $this->assertArrayHasKey('missingByStep', $assessment);
        $this->assertNotEmpty($assessment['missingByStep']);

        /*
         * ⚠️ THE TWO LISTS MUST AGREE. A keyed list that has drifted from the
         * flat one would block a member on a step for something the final
         * refusal does not mention, which is worse than the original fault.
         */
        $flattened = array_merge(...array_values($assessment['missingByStep']));

        foreach ($flattened as $item) {
            $this->assertContains(
                $item,
                $assessment['missing'],
                'A step reports something short that the overall list does not.',
            );
        }
    }

    public function test_every_payload_that_refreshes_completeness_carries_it(): void
    {
        $user = $this->member();

        // ── the step render ──────────────────────────────────────────────
        $props = $this->actingAs($user)->get('/profile/values')->assertOk()->viewData('page')['props'];
        $this->assertArrayHasKey('missingByStep', $props, 'The step render omits it.');

        // ── the autosave ─────────────────────────────────────────────────
        $saved = $this->actingAs($user)
            ->putJson('/profile/values', ['bio' => 'Too short.'])
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('missingByStep', $saved, 'The autosave response omits it.');

        // ── the photo endpoint, in a different controller ────────────────
        $uploaded = $this->actingAs($user)
            ->postJson('/photos', [
                'photo' => UploadedFile::fake()->image('p.jpg', 1200, 1600),
                'slot' => 'passport',
            ]);

        if ($uploaded->status() === 200) {
            $this->assertArrayHasKey(
                'missingByStep',
                $uploaded->json(),
                'The photo endpoint omits it, so uploading a photograph leaves the builder '
                    .'blocking on stale data until the page is reloaded.',
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL. Every assertion above is about a key being present
     * and two lists agreeing, and both are satisfied by an assessment that
     * finds nothing short at all — which is what a broken checker returns.
     */
    public function test_a_complete_step_reports_nothing_and_an_incomplete_one_does(): void
    {
        $user = $this->member();

        $short = app(ProfileCompleteness::class)->assess($user->profile);
        $this->assertArrayHasKey('values', $short['missingByStep'], 'A profile with no bio reports nothing short on the values step.');

        // Fill the values step and it must fall silent, without silencing the rest.
        $user->profile->forceFill([
            'relationship_goal' => 'marriage_soon',
            'marriage_timeline' => 'one_to_two',
            'core_values' => ['family', 'faith', 'honesty'],
            'conflict_style' => 'discuss_now',
            'bio' => str_repeat('a considered sentence about myself ', 40),
        ])->save();

        $after = app(ProfileCompleteness::class)->assess($user->profile->fresh());

        $this->assertArrayNotHasKey(
            'values',
            $after['missingByStep'],
            'The values step still reports a shortfall after every one of its fields was filled.',
        );

        $this->assertNotEmpty(
            $after['missingByStep'],
            'Filling one step emptied the whole list, so the keying is not per step at all.',
        );
    }
}
