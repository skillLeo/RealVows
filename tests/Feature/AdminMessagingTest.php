<?php

namespace Tests\Feature;

use App\Models\AdminMessage;
use App\Models\Guardian;
use App\Models\Profile;
use App\Models\User;
use App\Support\ReviewerScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ ADMIN MAY TALK TO ONE PERSON. NEVER BETWEEN TWO.
 *
 * RealVows introduces people and then gets out of the way. A staff member who
 * reads a message from one member and types its contents to another has built
 * member-to-member messaging by hand, with themselves as the transport.
 */
class AdminMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function staff(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    private function member(string $gender = 'woman'): User
    {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id, 'gender' => $gender, 'first_name' => 'Aisha']);

        return $user->fresh();
    }

    /**
     * ⚠️ THE CONTROL THAT CANNOT BE ENFORCED BY A SCHEMA MUST BE VISIBLE WHERE
     * IT IS BROKEN.
     *
     * Nothing stops staff reading one thread and typing its contents into
     * another. The sentence above the compose box is the whole control, so if
     * it stops being rendered the rule has silently gone.
     */
    public function test_the_relay_prohibition_is_rendered_above_the_compose_box(): void
    {
        $member = $this->member();

        $body = html_entity_decode(
            $this->actingAs($this->staff())->get("/admin/messages/{$member->id}")->assertOk()->getContent(),
            ENT_QUOTES | ENT_HTML5
        );

        $this->assertStringContainsString(
            AdminMessage::RELAY_PROHIBITION,
            $body,
            'The relay prohibition is not on the page. It is the only control against staff '
                .'passing messages between members, and a schema cannot enforce it.'
        );
    }

    /**
     * ⚠️ THE RULE LIVES IN ONE PLACE. A template that typed its own copy of the
     * sentence would drift from the model, and a search for the rule would find
     * markup instead of the rule.
     */
    public function test_the_prohibition_text_comes_from_the_model(): void
    {
        $this->assertStringContainsString('Never pass a message between two members', AdminMessage::RELAY_PROHIBITION);

        $component = file_get_contents(base_path('resources/js/Pages/Admin/Messages/Show.vue'));

        $this->assertStringNotContainsString(
            'Never pass a message between two members',
            $component,
            'The component hardcodes the prohibition instead of rendering the one from the '
                .'model, so the two can drift apart.'
        );
    }

    public function test_staff_can_write_to_a_member(): void
    {
        $member = $this->member();

        $this->actingAs($this->staff())
            ->post("/admin/messages/{$member->id}", ['body' => 'Could you re-take the photograph?'])
            ->assertRedirect();

        $this->assertDatabaseHas('admin_messages', [
            'counterparty_id' => $member->id,
            'from_staff' => true,
        ]);
    }

    /**
     * ⚠️ ONE COUNTERPARTY. The schema has no second party column, so a thread
     * cannot address two members even by mistake.
     */
    public function test_a_thread_has_exactly_one_counterparty(): void
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('admin_messages');

        $partyColumns = array_values(array_filter(
            $columns,
            fn (string $c) => str_contains($c, 'counterparty'),
        ));

        $this->assertSame(
            ['counterparty_id'],
            $partyColumns,
            'A second counterparty column has appeared. That is the first line of building '
                .'member-to-member messaging.'
        );
    }

    /** ⚠️ Gender separation applies to conversations, not only photographs. */
    public function test_a_male_reviewer_cannot_open_a_thread_with_a_female_member(): void
    {
        $member = $this->member('woman');

        $this->actingAs($this->staff(ReviewerScope::MALE))
            ->get("/admin/messages/{$member->id}")
            ->assertNotFound();

        $this->actingAs($this->staff(ReviewerScope::MALE))
            ->post("/admin/messages/{$member->id}", ['body' => 'hello'])
            ->assertNotFound();
    }

    /** ⚠️ POSITIVE CONTROL: the matching reviewer gets through. */
    public function test_the_matching_reviewer_can_open_the_thread(): void
    {
        $member = $this->member('woman');

        $this->actingAs($this->staff(ReviewerScope::FEMALE))
            ->get("/admin/messages/{$member->id}")
            ->assertOk();
    }

    /**
     * A trusted contact is scoped on the gender of the member they serve, not
     * their own — the conversation is about her.
     */
    public function test_a_trusted_contacts_thread_is_scoped_to_the_member_they_serve(): void
    {
        $member = $this->member('woman');

        $contactUser = User::factory()->create();
        $contactUser->assignRole('guardian');

        Guardian::create([
            'member_id' => $member->id,
            'user_id' => $contactUser->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'status' => Guardian::STATUS_VERIFIED,
        ]);

        $this->actingAs($this->staff(ReviewerScope::MALE))
            ->get("/admin/messages/{$contactUser->id}")
            ->assertNotFound();

        $this->actingAs($this->staff(ReviewerScope::FEMALE))
            ->get("/admin/messages/{$contactUser->id}")
            ->assertOk();
    }
}
