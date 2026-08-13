<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Profile;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\GuardianPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A member the product tells to act must be given something to act with.
 *
 * ⚠️ THE FAULT WAS ONE `v-else` AND IT STRANDED THE MEMBERS WHO NEEDED HELP.
 *
 * GuardianGate has always told somebody whose trusted contact was turned down,
 * or who has heard nothing for a fortnight, "You can name somebody else". The
 * nomination form rendered on `v-else` against "has a contact", so for exactly
 * those people it was not on the page. The product asked, and offered no way.
 *
 * The server supported replacement the whole time — GuardianService marks the
 * previous nomination `replaced` and creates a new one — so nothing was broken
 * anywhere a test was looking. Every existing test set up a member with NO
 * contact, which is the one state where the form did appear.
 *
 * That is the shape to remember: the tests were right about what they checked
 * and the product was unusable for the case none of them set up.
 */
class TrustedContactDeadEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        app(SettingsService::class)->set('guardian', GuardianPolicy::KEY, GuardianPolicy::REQUIRED_FEMALE);
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');
        Profile::factory()->create(['user_id' => $user->id, 'gender' => 'woman']);

        return $user->fresh();
    }

    private function nominate(User $member, string $status, array $extra = []): Guardian
    {
        return Guardian::create(array_merge([
            'member_id' => $member->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => $status,
        ], $extra));
    }

    /** @return array<string, mixed> */
    private function page(User $member): array
    {
        $response = $this->actingAs($member)->get('/trusted-contact');

        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    public function test_a_member_with_no_contact_is_offered_the_form(): void
    {
        /*
         * ⚠️ THE CONTROL. This is the state every existing test used, and it
         * always worked — which is precisely why the dead end survived. Without
         * it, a page that offered the form to nobody would satisfy every
         * assertion below.
         */
        $props = $this->page($this->member());

        $this->assertNull($props['current']);
        $this->assertSame('none_named', $props['state']['state']);
    }

    public function test_a_rejected_contact_leaves_the_member_able_to_name_another(): void
    {
        $member = $this->member();
        $this->nominate($member, Guardian::STATUS_REJECTED, [
            'rejection_reason' => 'The photograph did not show both faces clearly.',
        ]);

        $props = $this->page($member);

        $this->assertSame('rejected', $props['state']['state']);

        /*
         * ⚠️ THE REASON REACHES HER. Being told only that we "could not
         * confirm" somebody leaves nothing to fix — she cannot tell whether the
         * photograph was unclear, the person declined, or the relationship was
         * the problem.
         */
        $this->assertSame(
            'The photograph did not show both faces clearly.',
            $props['current']['rejectionReason'],
        );
    }

    public function test_a_silent_contact_eventually_lets_her_move_on(): void
    {
        $member = $this->member();

        $guardian = $this->nominate($member, Guardian::STATUS_INVITED);
        $guardian->forceFill(['created_at' => now()->subDays(30)])->save();

        $props = $this->page($member);

        $this->assertSame('stale', $props['state']['state'], 'A month of silence still reads as merely waiting.');
        $this->assertTrue($props['state']['mayReplace'] ?? false);
    }

    public function test_a_contact_still_being_checked_does_not_invite_a_replacement(): void
    {
        /*
         * The other direction, and it matters as much. Offering "name somebody
         * else" while our own team is mid-review would have her replace a
         * perfectly good contact because the page implied something was wrong.
         */
        $member = $this->member();
        $this->nominate($member, Guardian::STATUS_SUBMITTED);

        $props = $this->page($member);

        $this->assertSame('awaiting', $props['state']['state']);
        $this->assertArrayNotHasKey('mayReplace', $props['state']);
        $this->assertNull($props['current']['rejectionReason']);
    }

    public function test_replacing_marks_the_old_one_replaced_rather_than_deleting_it(): void
    {
        $member = $this->member();
        $old = $this->nominate($member, Guardian::STATUS_REJECTED);

        /*
         * ⚠️ assertSessionHasNoErrors BEFORE assertRedirect, AND THE ORDER OF
         * THAT DISCOVERY IS THE LESSON.
         *
         * I first wrote this with assertRedirect() alone and it passed while
         * the replacement had not happened — because a FAILED validation is
         * also a redirect, back to the form with errors. An assertion that
         * cannot tell success from failure is the shape this project keeps
         * finding, and I had just written another one.
         */
        $this->actingAs($member)->post('/trusted-contact', [
            'name' => 'Ibrahim Adeyemi',
            /*
             * ⚠️ THE LIST IS MALE ONLY, AND THAT IS THE DESIGN RATHER THAN AN
             * OVERSIGHT. Father, brother, paternal grandfather, paternal uncle
             * — the wali. I first wrote 'mother' here and the validator was
             * right to refuse it.
             */
            'relationship' => 'brother',
            'email' => 'ibrahim@example.com',
            'phone' => '+18765550123',
        ])->assertSessionHasNoErrors()->assertRedirect();

        /*
         * ⚠️ THE OLD ROW SURVIVES. The support flow that judges whether a
         * trusted contact was unfair reads the history, and a member who has
         * been through three contacts is a different situation from one on her
         * first — which is exactly what that flow exists to notice.
         */
        $this->assertSame(Guardian::STATUS_REPLACED, $old->fresh()->status);
        $this->assertSame(2, Guardian::where('member_id', $member->id)->count());
    }
}
