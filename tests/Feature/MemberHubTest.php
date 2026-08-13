<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\GuardianPolicy;
use App\Support\MemberHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every row on the member hub must open for the member it is rendered to.
 *
 * ⚠️ THE SAME WALK THE ADMIN SIDEBAR GETS, FOR THE SAME REASON.
 *
 * `/trusted-contact` 404s when the policy does not offer it, so a row built
 * from the gender rather than from the policy would put a dead link on the
 * account page of every member the moment somebody changed one dropdown. That
 * is not a hypothetical: the entire trusted-contact system was invisible for
 * weeks because one setting had no UI, and the fix was to change that setting.
 * Rows have to survive it being changed again.
 */
class MemberHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function member(string $gender = 'woman'): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');
        Profile::factory()->create(['user_id' => $user->id, 'gender' => $gender]);

        return $user->fresh();
    }

    private function setPolicy(string $level): void
    {
        app(SettingsService::class)->set('guardian', GuardianPolicy::KEY, $level);
    }

    /** @return list<array{key: string, label: string, href: string, icon: string, status: string|null}> */
    private function rows(User $user): array
    {
        return collect(MemberHub::for($user))->flatMap(fn (array $g) => $g['items'])->all();
    }

    public function test_every_row_opens_for_the_member_it_is_shown_to(): void
    {
        $this->setPolicy(GuardianPolicy::REQUIRED_FEMALE);

        $walked = 0;

        foreach (['woman', 'man'] as $gender) {
            $user = $this->member($gender);

            foreach ($this->rows($user) as $row) {
                $response = $this->actingAs($user)->followingRedirects()->get($row['href']);

                $this->assertSame(
                    200,
                    $response->getStatusCode(),
                    "A {$gender} sees {$row['label']} ({$row['href']}), which answered {$response->getStatusCode()}."
                );

                $walked++;
            }
        }

        /*
         * ⚠️ PROVE THE WALK WALKED. A hub returning nothing satisfies every
         * assertion in the loop above by never entering it, and this file would
         * report the account page as sound while it had no rows on it at all.
         */
        $this->assertGreaterThanOrEqual(8, $walked, "Only {$walked} rows were checked across both genders.");
    }

    public function test_the_trusted_contact_row_disappears_when_the_policy_is_off(): void
    {
        /*
         * The control pair. With the policy off, /trusted-contact 404s — so a
         * row that stayed would be a dead link on every member's account page.
         */
        $this->setPolicy(GuardianPolicy::OFF);

        $user = $this->member('woman');

        $this->actingAs($user)->get('/trusted-contact')->assertNotFound();

        $this->assertNotContains(
            'trusted-contact',
            array_column($this->rows($user), 'key'),
            'The hub offers a trusted-contact row while the route 404s.'
        );
    }

    public function test_the_trusted_contact_row_appears_when_the_policy_offers_it(): void
    {
        // The other direction, so the row cannot simply have been deleted.
        $this->setPolicy(GuardianPolicy::REQUIRED_FEMALE);

        $user = $this->member('woman');

        $this->assertContains('trusted-contact', array_column($this->rows($user), 'key'));
    }

    public function test_a_woman_who_has_named_nobody_is_told_why_it_matters(): void
    {
        $this->setPolicy(GuardianPolicy::REQUIRED_FEMALE);

        $row = collect($this->rows($this->member('woman')))->firstWhere('key', 'trusted-contact');

        /*
         * ⚠️ THE STATUS IS THE POINT OF THE ROW. Without it this is a link with
         * a label, and the member has to open the page to discover that nothing
         * can happen until they act. The status map previously used state names
         * that did not exist, so every waiting member fell through to null —
         * silently, on the one row where waiting is the whole subject.
         */
        $this->assertNotNull($row['status']);
        $this->assertStringContainsString('introduce', $row['status']);
    }

    public function test_a_man_is_offered_it_without_being_told_it_is_required(): void
    {
        $this->setPolicy(GuardianPolicy::REQUIRED_FEMALE);

        $row = collect($this->rows($this->member('man')))->firstWhere('key', 'trusted-contact');

        $this->assertNotNull($row, 'It is offered to everyone at this level.');

        /*
         * ⚠️ NO STATUS, AND THAT IS RIGHT RATHER THAN MISSING.
         *
         * The gate answers `not_required` for a man at this level and stops —
         * it does not go on to ask whether he has actually named anybody. So
         * any status here would be a guess: "Not added yet" is wrong for a man
         * who has added one, and "In place" is wrong for one who has not.
         *
         * I wrote this test expecting "Not added yet" and the code was right.
         * A row with no status reads as a plain destination, which is exactly
         * what it is for him.
         */
        $this->assertNull($row['status']);
    }

    public function test_no_group_renders_as_an_empty_heading(): void
    {
        $this->setPolicy(GuardianPolicy::OFF);

        foreach (MemberHub::for($this->member('woman')) as $group) {
            $this->assertNotEmpty(
                $group['items'],
                "The '{$group['label']}' group would render as a heading with nothing under it."
            );
        }
    }
}
