<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\GuardianDecision;
use App\Models\Profile;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\ReviewerScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Support, and the one ticket type the table exists for.
 */
class SupportFlowTest extends TestCase
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
        Profile::factory()->create(['user_id' => $user->id, 'gender' => $gender, 'first_name' => 'Aisha']);

        return $user->fresh();
    }

    private function staff(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    public function test_a_member_can_raise_a_ticket(): void
    {
        $this->actingAs($this->member())
            ->post('/support', ['type' => 'account', 'body' => 'I cannot change my number.'])
            ->assertRedirect();

        $this->assertDatabaseHas('support_tickets', ['type' => 'account', 'status' => 'open']);
    }

    /**
     * ⚠️ A TRUSTED CONTACT DOES NOT GET THE ROUTE THAT EXISTS TO CHALLENGE
     * THEM. A form that offers it does not know who is filling it in.
     */
    public function test_a_trusted_contact_cannot_raise_an_unfair_contact_ticket(): void
    {
        $member = $this->member();

        $contactUser = User::factory()->create();
        $contactUser->assignRole('guardian');

        Guardian::create([
            'member_id' => $member->id,
            'user_id' => $contactUser->id,
            'name' => 'Yusuf',
            'relationship' => 'father',
            'status' => Guardian::STATUS_VERIFIED,
        ]);

        $this->actingAs($contactUser->fresh())
            ->post('/support', ['type' => 'guardian_unfair', 'body' => 'Trying it on.'])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('support_tickets', 0);
    }

    /** ⚠️ POSITIVE CONTROL: a member CAN raise it, so the refusal above means refusal. */
    public function test_a_member_can_raise_an_unfair_contact_ticket(): void
    {
        $this->actingAs($this->member())
            ->post('/support', ['type' => 'guardian_unfair', 'body' => 'He declines everything.'])
            ->assertRedirect();

        $this->assertDatabaseHas('support_tickets', ['type' => 'guardian_unfair']);
    }

    /**
     * ⚠️ THE ORDERING IS THE POLICY. Somebody whose introductions have stopped
     * cannot wait behind a billing question raised an hour earlier.
     */
    public function test_unfair_contact_tickets_are_listed_first(): void
    {
        $older = $this->member();
        $newer = $this->member();

        SupportTicket::create([
            'user_id' => $older->id, 'type' => 'billing',
            'body' => 'Older billing question.', 'status' => 'open',
        ])->forceFill(['created_at' => now()->subDays(3)])->save();

        SupportTicket::create([
            'user_id' => $newer->id, 'type' => 'guardian_unfair',
            'body' => 'Newer but urgent.', 'status' => 'open',
        ]);

        $body = html_entity_decode(
            $this->actingAs($this->staff())->get('/admin/support')->assertOk()->getContent(),
            ENT_QUOTES | ENT_HTML5
        );

        $this->assertLessThan(
            strpos($body, 'Older billing question.'),
            strpos($body, 'Newer but urgent.'),
            'A billing question raised earlier is listed above somebody whose introductions '
                .'have stopped.'
        );
    }

    /** ⚠️ B9: after five unexplained declines she is told the route exists. */
    public function test_the_unfair_route_is_offered_after_five_unexplained_declines(): void
    {
        $member = $this->member();

        $guardian = Guardian::create([
            'member_id' => $member->id, 'name' => 'Yusuf',
            'relationship' => 'father', 'status' => Guardian::STATUS_VERIFIED,
        ]);

        $body = $this->actingAs($member)->get('/support')->assertOk()->getContent();
        $this->assertStringContainsString('"suggestUnfair":false', html_entity_decode($body, ENT_QUOTES | ENT_HTML5));

        for ($i = 0; $i < 5; $i++) {
            GuardianDecision::create([
                'guardian_id' => $guardian->id, 'gate' => Guardian::GATE_INTRODUCTION,
                'decision' => GuardianDecision::DECLINED, 'reason' => null, 'decided_at' => now(),
            ]);
        }

        $after = html_entity_decode(
            $this->actingAs($member)->get('/support')->assertOk()->getContent(),
            ENT_QUOTES | ENT_HTML5
        );

        $this->assertStringContainsString('"suggestUnfair":true', $after);
    }

    /** ⚠️ Gender separation reaches the inbox too. */
    public function test_a_male_reviewer_does_not_see_a_female_members_ticket(): void
    {
        $member = $this->member('woman');

        SupportTicket::create([
            'user_id' => $member->id, 'type' => 'account',
            'body' => 'HER-PRIVATE-TICKET', 'status' => 'open',
        ]);

        $body = $this->actingAs($this->staff(ReviewerScope::MALE))->get('/admin/support')->getContent();
        $this->assertStringNotContainsString('HER-PRIVATE-TICKET', html_entity_decode($body, ENT_QUOTES | ENT_HTML5));

        // ⚠️ CONTROL
        $ok = $this->actingAs($this->staff(ReviewerScope::FEMALE))->get('/admin/support')->getContent();
        $this->assertStringContainsString('HER-PRIVATE-TICKET', html_entity_decode($ok, ENT_QUOTES | ENT_HTML5));
    }

    public function test_closing_a_ticket_records_who_closed_it(): void
    {
        $ticket = SupportTicket::create([
            'user_id' => $this->member()->id, 'type' => 'account',
            'body' => 'Something.', 'status' => 'open',
        ]);

        $staff = $this->staff();

        $this->actingAs($staff)->post("/admin/support/{$ticket->id}")->assertRedirect();

        $fresh = $ticket->fresh();
        $this->assertSame(SupportTicket::STATUS_RESOLVED, $fresh->status);
        $this->assertSame($staff->id, $fresh->resolved_by);
    }
}
