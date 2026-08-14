<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Services\Ai\ProfileWriter;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The writer end to end: it suggests, it never saves, and an unfaithful rewrite
 * never leaves the service.
 */
class ProfileWriterTest extends TestCase
{
    use RefreshDatabase;

    private const BIO = 'i am teacher in Kingston. i like cooking and reading books. '
        .'i want family one day and someone who is kind to people.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        Http::preventStrayRequests();
        RateLimiter::clear('profile-writer:1');
    }

    private function member(): User
    {
        $user = User::factory()->create();
        Profile::factory()->woman()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    private function enable(): void
    {
        app(SettingsService::class)->set('ai', 'openai_api_key', 'sk-test-key');
    }

    private function fakeRewrite(string $text): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['rewrite' => $text])]]],
            ]),
        ]);
    }

    /** ⚠️ With no key the endpoint does not exist, and nothing is called. */
    public function test_with_no_api_key_the_endpoint_is_absent(): void
    {
        $this->actingAs($this->member())
            ->postJson('/profile/rewrite', ['bio' => self::BIO])
            ->assertNotFound();
    }

    public function test_a_faithful_rewrite_is_returned_with_the_original(): void
    {
        $this->enable();
        $this->fakeRewrite('I am a teacher in Kingston. I enjoy cooking and reading. '
            .'I would like a family one day, and someone kind to people.');

        $response = $this->actingAs($this->member())
            ->postJson('/profile/rewrite', ['bio' => self::BIO])
            ->assertOk();

        $this->assertNotNull($response->json('rewrite'));
        $this->assertSame(self::BIO, $response->json('original'));
    }

    /**
     * ⚠️ THE ONE THAT MATTERS. An unfaithful rewrite is DISCARDED, not shown
     * with a caution — a member halfway through signing up will click accept.
     */
    public function test_an_invented_fact_never_reaches_the_member(): void
    {
        $this->enable();
        $this->fakeRewrite('I am a teacher at Immaculate Conception in Montego Bay with 3 dogs.');

        $response = $this->actingAs($this->member())
            ->postJson('/profile/rewrite', ['bio' => self::BIO])
            ->assertOk();

        $this->assertNull(
            $response->json('rewrite'),
            'A rewrite containing invented facts was offered to the member.'
        );

        $body = $response->getContent();
        foreach (['Immaculate', 'Montego', 'dogs'] as $invented) {
            $this->assertStringNotContainsString($invented, $body);
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL: the gate is not simply rejecting everything.
     *
     * ⚠️ THE FIXTURE HAD TO GROW, AND THAT IS THE POINT OF THE NEW RULE. It
     * returned a rewrite SHORTER than the original, which now counts as a
     * failure: the bio has a 150-word floor, so a member who is already short
     * and presses the button must not be moved further away from finishing.
     *
     * Every fact here is present in the original. Nothing is invented — the
     * sentences are simply allowed to breathe, which is exactly what the
     * instruction asks of the model.
     */
    public function test_the_gate_lets_a_faithful_rewrite_through(): void
    {
        $this->enable();
        $this->fakeRewrite(
            'I am a teacher in Kingston. I enjoy cooking, and I enjoy reading books. '
            .'I would like a family one day, and I am looking for somebody who is kind '
            .'to people.'
        );

        $this->assertNotNull(
            app(ProfileWriter::class)->rewrite(self::BIO),
            'The gate rejects everything, so the refusal above proves nothing.'
        );
    }

    /** ⚠️ A provider outage returns the member to their own words, not an error. */
    public function test_a_failing_provider_returns_no_suggestion_rather_than_an_error(): void
    {
        $this->enable();
        Http::fake(['api.openai.com/*' => Http::response('down', 500)]);

        $this->actingAs($this->member())
            ->postJson('/profile/rewrite', ['bio' => self::BIO])
            ->assertOk()
            ->assertJson(['rewrite' => null]);
    }

    /** ⚠️ IT SUGGESTS AND NEVER SAVES. */
    public function test_nothing_is_written_to_the_profile(): void
    {
        $this->enable();
        $this->fakeRewrite('I am a teacher in Kingston. I enjoy cooking and reading books.');

        $member = $this->member();
        $before = $member->profile->bio;

        $this->actingAs($member)->postJson('/profile/rewrite', ['bio' => self::BIO])->assertOk();

        $this->assertSame(
            $before,
            $member->profile->fresh()->bio,
            'The endpoint saved a rewrite. Pressing a button labelled "improve" is not consent '
                .'to change a profile.'
        );
    }

    /** ⚠️ Five a day. A rewrite costs real money on somebody else's account. */
    public function test_the_sixth_rewrite_in_a_day_is_refused(): void
    {
        $this->enable();
        $this->fakeRewrite('I am a teacher in Kingston. I enjoy cooking and reading books.');

        $member = $this->member();

        for ($i = 0; $i < ProfileWriter::DAILY_LIMIT; $i++) {
            $this->actingAs($member)->postJson('/profile/rewrite', ['bio' => self::BIO])->assertOk();
        }

        $this->actingAs($member)
            ->postJson('/profile/rewrite', ['bio' => self::BIO])
            ->assertStatus(429)
            ->assertJsonFragment(['message' => 'You have used your rewrites for today. Your own '
                .'words are saved and nothing has been lost.']);
    }

    /**
     * ⚠️ THE LIMIT IS PER MEMBER, NOT PER ADDRESS. Two members on one household
     * connection are two people.
     */
    public function test_one_members_limit_does_not_exhaust_anothers(): void
    {
        $this->enable();
        $this->fakeRewrite('I am a teacher in Kingston. I enjoy cooking and reading books.');

        $first = $this->member();
        $second = $this->member();

        for ($i = 0; $i < ProfileWriter::DAILY_LIMIT; $i++) {
            $this->actingAs($first)->postJson('/profile/rewrite', ['bio' => self::BIO])->assertOk();
        }

        $this->actingAs($second)
            ->postJson('/profile/rewrite', ['bio' => self::BIO])
            ->assertOk();
    }

    /** ⚠️ The original column exists so a false claim can be traced to its author. */
    public function test_the_profile_can_store_the_members_own_words_separately(): void
    {
        $this->assertTrue(Schema::hasColumn('profiles', 'bio_original'));
        $this->assertTrue(Schema::hasColumn('profiles', 'bio_rewritten_by'));
    }

    /**
     * ⚠️ A COLUMN NOTHING WRITES TO IS WORSE THAN NO COLUMN.
     *
     * `bio_original` existed for a session with no save path populating it. The
     * audit trail looked present and was empty, which is the shape that answers
     * "can we prove what she typed?" with a confident yes right up until
     * somebody checks.
     */
    public function test_accepting_a_rewrite_records_what_the_member_typed(): void
    {
        $member = $this->member();

        $this->actingAs($member)->putJson('/profile/values', [
            'bio' => 'I am a teacher in Kingston and I enjoy cooking.',
            'bio_original' => 'i am teacher in Kingston. i like cooking.',
        ])->assertOk();

        $profile = $member->profile->fresh();

        $this->assertSame('i am teacher in Kingston. i like cooking.', $profile->bio_original);
        $this->assertSame('profile_writer', $profile->bio_rewritten_by);
        $this->assertNotNull($profile->bio_rewritten_at);
    }

    /**
     * ⚠️ WRITTEN ONCE, NEVER OVERWRITTEN.
     *
     * A member who accepts a rewrite, edits it, and saves again must not have
     * their original replaced by the rewrite they have since edited. That would
     * quietly record the machine's words as theirs, which is the exact opposite
     * of what the column is for.
     */
    public function test_a_later_save_cannot_replace_the_original(): void
    {
        $member = $this->member();

        $this->actingAs($member)->putJson('/profile/values', [
            'bio' => 'A tidied version.',
            'bio_original' => 'the words she actually typed',
        ])->assertOk();

        $this->actingAs($member)->putJson('/profile/values', [
            'bio' => 'A tidied version, edited a bit.',
            'bio_original' => 'A tidied version.',
        ])->assertOk();

        $this->assertSame(
            'the words she actually typed',
            $member->profile->fresh()->bio_original,
            'A second save replaced the member\'s own words with a rewrite.'
        );
    }
}
