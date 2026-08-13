<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The overview counts only what the reader is already allowed to see.
 *
 * ⚠️ AN AGGREGATE IS INFORMATION ABOUT THE ROWS BEHIND IT.
 *
 * The admin is gender-separated, and that separation is the reason members
 * agree to be looked at. Handing a female reviewer a total member count, a
 * signup curve and a gender split describes the people she was specifically
 * not given access to. Nothing is disclosed one row at a time, which is
 * precisely why it is easy to ship.
 *
 * Every case is a pair: an owner must still see everything, or a dashboard
 * that had simply stopped counting would satisfy every leak assertion here.
 */
class AdminDashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        foreach (range(1, 7) as $i) {
            Profile::factory()->create(['user_id' => User::factory()->create()->id, 'gender' => 'woman']);
        }

        foreach (range(1, 3) as $i) {
            Profile::factory()->create(['user_id' => User::factory()->create()->id, 'gender' => 'man']);
        }
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function dashboardFor(User $user): array
    {
        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    public function test_an_owner_sees_the_whole_platform(): void
    {
        /*
         * ⚠️ THE CONTROL. Without it, a dashboard returning zero for everybody
         * would pass every scoping assertion below and this file would report
         * the separation as airtight.
         */
        $props = $this->dashboardFor($this->staff('super_admin'));

        $this->assertSame(10, $props['members']['total']);
        $this->assertFalse($props['scope']['restricted']);
        $this->assertNotNull($props['genderSplit'], 'An owner should see the breakdown.');
    }

    public function test_a_female_reviewer_counts_only_women(): void
    {
        $props = $this->dashboardFor($this->staff('reviewer_female'));

        $this->assertSame(7, $props['members']['total'], 'The count includes members outside the review scope.');
        $this->assertTrue($props['scope']['restricted']);
    }

    public function test_a_male_reviewer_counts_only_men(): void
    {
        // The other direction, so the scope cannot simply be stuck on one list.
        $props = $this->dashboardFor($this->staff('reviewer_male'));

        $this->assertSame(3, $props['members']['total']);
    }

    public function test_the_gender_breakdown_is_withheld_from_a_scoped_reviewer(): void
    {
        $props = $this->dashboardFor($this->staff('reviewer_female'));

        $this->assertNull(
            $props['genderSplit'],
            'A scoped reviewer is being shown the split across the whole platform.'
        );
    }

    public function test_the_signup_trend_is_scoped_too(): void
    {
        /*
         * The curve is the easiest thing to forget, because it looks like a
         * shape rather than like data about people.
         */
        $owner = collect($this->dashboardFor($this->staff('super_admin'))['signups'])->sum('count');
        $female = collect($this->dashboardFor($this->staff('reviewer_female'))['signups'])->sum('count');

        $this->assertSame(10, $owner);
        $this->assertSame(7, $female, 'The signup curve is counting members outside the review scope.');
    }

    public function test_the_scope_is_stated_on_the_page(): void
    {
        /*
         * A number without its scope is a number somebody will quote. The
         * reviewer must be able to read that 7 is her half, not the platform.
         */
        $props = $this->dashboardFor($this->staff('reviewer_female'));

        $this->assertStringContainsString('women', strtolower($props['scope']['label']));
    }

    public function test_every_day_of_the_trend_is_present_including_the_quiet_ones(): void
    {
        /*
         * ⚠️ Grouping alone returns only days that had a signup, and a chart
         * drawn from that closes the gaps — ten signups across a fortnight
         * would render as ten consecutive busy days. The quiet is the
         * information.
         */
        $props = $this->dashboardFor($this->staff('super_admin'));

        $this->assertCount(14, $props['signups']);
        $this->assertSame(
            array_map(fn (array $row) => $row['day'], $props['signups']),
            collect(range(13, 0))->map(fn (int $ago) => now()->subDays($ago)->toDateString())->all(),
        );
    }
}
