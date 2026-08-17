<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Setting;
use App\Models\User;
use App\Rules\NoPressureLanguage;
use App\Support\MemberFacingSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ⚠️ THE ONE HOLE IN THE ANTI-DRIFT GUARANTEE.
 *
 * "No urgency, no scarcity, no gamification" is a standing rule for this
 * product, and every guard enforcing it reads the repository: forbidden terms
 * in `resources/`, banned phrasing in the copy deck, effort language in the
 * emails. All of them run in CI against files.
 *
 * ⚠️ NONE OF THEM CAN SEE A SETTINGS ROW. The package badges are free text in
 * the admin panel, so somebody can type "Only 3 left!" and it reaches every
 * member on the pricing page without a commit, without CI, and without anybody
 * reading a line of it. Same for the site name, the sender name on every email,
 * the rejection reason a refused member is shown, and the trading name on a
 * receipt.
 *
 * ⚠️ AND FOR THE ONE PLACE STAFF WRITE FREELY TO ONE PERSON. A support reply is
 * where "act fast" arrives first, written helpfully, by somebody who never read
 * the rule.
 */
class NoPressureLanguageReachesMembersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    /**
     * ⚠️ super_admin, BECAUSE `settings.update` IS NOT AN `admin` PERMISSION.
     *
     * A plain admin posting to the settings endpoint is refused by the
     * middleware before validation runs, so every "the save was rejected"
     * assertion here would pass on a 403 and prove nothing about the language
     * rule at all.
     */
    private function admin(): User
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('super_admin');

        return $admin->fresh();
    }

    /** Staff messaging needs verifications.review, which `admin` does hold. */
    private function staff(): User
    {
        $staff = User::factory()->create(['email_verified_at' => now()]);
        $staff->assignRole('admin');

        return $staff->fresh();
    }

    private function saveBadge(string $value)
    {
        return $this->actingAs($this->admin())->post('/admin/settings', [
            'group' => 'packages',
            'values' => ['octet_badge' => $value],
        ]);
    }

    // ── The badge, refused ───────────────────────────────────────────────

    #[DataProvider('pressureBadges')]
    public function test_a_badge_that_manufactures_pressure_is_refused(string $badge): void
    {
        $this->saveBadge($badge)->assertSessionHasErrors('values.octet_badge');

        $this->assertNotSame(
            $badge,
            Setting::where('group', 'packages')->where('key', 'octet_badge')->value('value'),
            "\"{$badge}\" was saved and now reaches every member on the pricing page.",
        );
    }

    public static function pressureBadges(): array
    {
        return [
            'countdown' => ['Ending soon'],
            'deadline' => ['Last chance'],
            'hurry' => ['Hurry, offer closes'],
            'stock count' => ['Only 3 left'],
            'stock count in words' => ['Only two left'],
            'spaces' => ['4 spaces remaining'],
            'selling out' => ['Selling fast'],
            'limited' => ['Limited time only'],
            'almost gone' => ['Almost gone'],
            'do not miss' => ["Don't miss out"],
        ];
    }

    // ── ⚠️ The badge, allowed. The half that keeps the rule usable ───────

    /**
     * ⚠️ POSITIVE CONTROL, AND THE MORE IMPORTANT DIRECTION.
     *
     * A rule that refused everything would satisfy every assertion above and
     * would stop the client editing his own pricing page. The person it blocks
     * is a colleague trying to write something true, and what they do next is
     * find a way around it.
     *
     */
    #[DataProvider('honestBadges')]
    public function test_an_honest_badge_saves(string $badge): void
    {
        $this->saveBadge($badge)->assertSessionHasNoErrors();

        $this->assertSame(
            $badge,
            Setting::where('group', 'packages')->where('key', 'octet_badge')->value('value'),
            "\"{$badge}\" was refused. It says something true and hurries nobody.",
        );
    }

    public static function honestBadges(): array
    {
        return [
            'the current one' => ['Best value'],
            'social proof' => ['Most chosen'],
            'plain' => ['Eight introductions'],
            'free period' => ['Free right now'],
            // ⚠️ Contains a number, and counts nothing. The rule reads shapes,
            // not digits, or every price and credit count would be an offence.
            'a number that is not stock' => ['Eight introductions, one price'],
            'the word only, honestly used' => ['The only thing you pay for'],
        ];
    }

    // ── Every other field a member reads ─────────────────────────────────

    public function test_the_same_rule_covers_every_member_facing_setting(): void
    {
        foreach (MemberFacingSettings::FIELDS as $path => $label) {
            [$group, $key] = explode('.', $path, 2);

            $response = $this->actingAs($this->admin())->post('/admin/settings', [
                'group' => $group,
                'values' => [$key => 'Only 2 left, act fast'],
            ]);

            /*
             * ⚠️ NO CUSTOM MESSAGE HERE. assertSessionHasErrors takes the ERROR
             * BAG as its third argument, not a description, so passing one
             * looks for a bag by that name, finds nothing, and fails every
             * field including the ones that are working.
             */
            $response->assertSessionHasErrors("values.{$key}");

            $this->assertNotSame(
                'Only 2 left, act fast',
                Setting::where('group', $group)->where('key', $key)->value('value'),
                "{$path} saved urgency language. A member reads it.",
            );
        }
    }

    /**
     * ⚠️ THE LIST HAS TO NAME SETTINGS THAT EXIST.
     *
     * A renamed key leaves a dead entry here, and a dead entry protects
     * nothing while looking exactly like protection. This is the same failure
     * as a stale allow-list, pointed the other way.
     */
    public function test_every_field_named_in_the_list_is_a_real_setting(): void
    {
        foreach (MemberFacingSettings::FIELDS as $path => $label) {
            [$group, $key] = explode('.', $path, 2);

            $this->assertTrue(
                Setting::where('group', $group)->where('key', $key)->exists(),
                "MemberFacingSettings names {$path}, which is not a setting. Either it was "
                    .'renamed and this entry now guards nothing, or it was removed.',
            );
        }
    }

    /**
     * ⚠️ AND EVERY PACKAGE LABEL AND BADGE MUST BE IN IT.
     *
     * Adding a fourth package is exactly the moment somebody adds three
     * settings and forgets this list, and the new one would be the unguarded
     * one.
     */
    public function test_no_package_label_or_badge_is_left_out(): void
    {
        $unguarded = Setting::where('group', 'packages')
            ->get()
            ->filter(fn (Setting $s) => str_ends_with($s->key, '_label') || str_ends_with($s->key, '_badge'))
            ->reject(fn (Setting $s) => MemberFacingSettings::covers('packages', $s->key))
            ->pluck('key')
            ->all();

        $this->assertSame([], $unguarded, 'These package fields reach members and are unguarded.');
    }

    // ── Staff writing directly to one member ─────────────────────────────

    public function test_a_support_reply_cannot_hurry_a_member(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $member->assignRole('member');
        Profile::factory()->create(['user_id' => $member->id]);

        $this->actingAs($this->staff())
            ->post("/admin/messages/{$member->id}", ['body' => 'Please act fast, only 2 spaces left.'])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('admin_messages', 0);
    }

    /** ⚠️ POSITIVE CONTROL. Staff must still be able to answer somebody. */
    public function test_an_ordinary_support_reply_still_sends(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $member->assignRole('member');
        Profile::factory()->create(['user_id' => $member->id]);

        $this->actingAs($this->staff())
            ->post("/admin/messages/{$member->id}", [
                'body' => 'Thank you for writing. Your photographs are with a reviewer and we '
                    .'will come back to you as soon as somebody has looked at them.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('admin_messages', 1);
    }

    // ── ⚠️ The reader itself ─────────────────────────────────────────────

    /**
     * ⚠️ EVERY ASSERTION ABOVE DEPENDS ON THIS RULE MATCHING ANYTHING AT ALL.
     *
     * "The save was refused" and "the save went through" are both satisfied by
     * a rule whose pattern list has been emptied, commented out, or broken by a
     * bad regex — and the broken version fails open, which is the direction
     * nobody notices.
     */
    public function test_the_reader_reads(): void
    {
        $this->assertGreaterThan(20, count(NoPressureLanguage::patterns()));

        foreach (NoPressureLanguage::patterns() as $pattern) {
            $this->assertNotFalse(
                @preg_match($pattern, 'a harmless sentence'),
                "The pattern {$pattern} is not valid, so it silently matches nothing.",
            );
        }
    }
}
