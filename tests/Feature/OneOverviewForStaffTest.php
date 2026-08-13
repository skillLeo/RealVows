<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff have one overview, whichever of the three URLs they type.
 *
 * ⚠️ THE FAULT THIS FIXES WAS NOT A 404. IT WAS TWO PAGES THAT BOTH LOOKED
 * RIGHT.
 *
 * /dashboard, /admin and /admin/dashboard existed simultaneously, and which
 * one you got depended on how you arrived. The sidebar's own Matches link
 * pointed at /dashboard, so a reviewer could sit in front of a page headed
 * "Your introductions" showing 0 — their own empty member account — and read
 * it as a statement about the platform. Nothing errored. Nothing looked
 * broken. The number was simply about somebody else.
 *
 * Both directions are asserted: a member must still get their own dashboard,
 * or a redirect that fired for everybody would satisfy every staff assertion
 * here while quietly taking the product away from its members.
 */
class OneOverviewForStaffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        Profile::factory()->create(['user_id' => $user->id, 'gender' => 'woman']);

        return $user->fresh();
    }

    public function test_staff_asking_for_the_member_dashboard_get_the_admin_one(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get('/dashboard')
            ->assertRedirect('/admin');
    }

    public function test_a_reviewer_is_redirected_too(): void
    {
        // The other staff role, so this cannot be true only for the owner.
        $this->actingAs($this->userWithRole('admin'))
            ->get('/dashboard')
            ->assertRedirect('/admin');
    }

    public function test_the_alias_lands_on_the_same_page(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get('/admin/dashboard')
            ->assertRedirect('/admin');
    }

    public function test_all_three_urls_end_on_the_admin_overview(): void
    {
        $user = $this->userWithRole('super_admin');

        foreach (['/dashboard', '/admin', '/admin/dashboard'] as $url) {
            $response = $this->actingAs($user)->followingRedirects()->get($url);

            $response->assertOk();

            $this->assertSame(
                'Admin/Dashboard',
                $response->viewData('page')['component'],
                "{$url} did not end on the admin overview."
            );
        }
    }

    public function test_a_member_still_gets_their_own_dashboard(): void
    {
        /*
         * ⚠️ THE CONTROL, AND THE ONE THAT MATTERS MOST. A redirect written
         * without a condition — or with a condition that quietly matches
         * everybody — passes every assertion above and removes the dashboard
         * from every member of the product.
         */
        $response = $this->actingAs($this->userWithRole('member'))->get('/dashboard');

        $response->assertOk();

        $this->assertSame('Dashboard', $response->viewData('page')['component']);
    }

    public function test_a_member_cannot_reach_the_admin_overview(): void
    {
        $this->actingAs($this->userWithRole('member'))
            ->get('/admin')
            ->assertNotFound();
    }
}
