<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\ProfilePreference;
use App\Models\User;
use App\Models\Verification;
use App\Services\Matching\CandidatePool;
use App\Support\FaithRule;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ THE MOST CONSEQUENTIAL RULE IN THE PRODUCT, AND IT WAS NOT IN THE CODE.
 *
 * The candidate query held twelve conditions and faith was not one of them. It
 * lived in Lifestyle scoring, as part of seventeen points out of a hundred,
 * which means a mismatch could be outweighed by height and hobbies and the
 * introduction was still made.
 *
 * So a Muslim woman on this platform was introduced to non-Muslim men, every
 * night, with nothing on any screen telling her it could happen. Forbidden by
 * consensus with no difference of opinion in any school — and it was the
 * ordinary behaviour of the code, not a misuse of it.
 *
 * ── THE RULE ──────────────────────────────────────────────────────────────
 *
 *  · Muslim woman → Muslim men only. No exception, no override, no setting.
 *  · Muslim man   → Muslim, Christian or Jewish women.
 *  · Everybody else carries no faith restriction.
 *
 * ⚠️ ENFORCED IN THE QUERY. An ineligible pairing is ABSENT from the candidate
 * set, never filtered out of it afterwards. A filter is one forgotten call site
 * from a leak, and here a leak is a forbidden marriage introduction.
 */
class FaithIsAHardFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    private function member(string $gender, string $seeking, string $religion): Profile
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('member');

        $profile = Profile::factory()->create([
            'user_id' => $user->id,
            'gender' => $gender,
            'seeking' => $seeking,
            'religion' => $religion,
            'submitted_at' => now(),
            'date_of_birth' => now()->subYears(30),
            'city' => 'Kingston',
            'country' => 'Jamaica',
        ]);

        ProfilePreference::factory()->create([
            'profile_id' => $profile->id,
            'age_min' => 18,
            'age_max' => 70,
            'distance_preference' => 'anywhere',
        ]);

        Verification::create([
            'profile_id' => $profile->id,
            'status' => Verification::STATUS_VERIFIED,
            'code' => 'AB12CD',
            'verified_at' => now(),
        ]);

        /*
         * ⚠️ WOMEN NEED A VERIFIED TRUSTED CONTACT TO BE IN THE POOL AT ALL.
         *
         * `guardian_requirement` is `required_female`, so without this every
         * woman is excluded by condition twelve and every "she was not offered
         * him" assertion in this file would pass for a reason that has nothing
         * to do with faith. Three tests failed exactly that way before it was
         * added, which is the only reason it is here rather than assumed.
         */
        if (in_array($gender, ['woman', 'non_binary'], true)) {
            \App\Models\Guardian::create([
                'member_id' => $user->id,
                'name' => 'Yusuf Rahman',
                'relationship' => 'father',
                'email' => 'guardian'.$user->id.'@gmail.com',
                'phone' => '+18761234567',
                'status' => \App\Models\Guardian::STATUS_VERIFIED,
                'is_appointed' => false,
                'verified_at' => now(),
            ]);
        }

        return $profile->fresh();
    }

    private function candidatesFor(Profile $seeker): array
    {
        return app(CandidatePool::class)->query($seeker)->pluck('profiles.id')->all();
    }

    // ── The rule that has no exception ───────────────────────────────────

    public function test_a_muslim_woman_is_never_offered_a_non_muslim_man(): void
    {
        $her = $this->member('woman', 'man', 'Islam');

        $christian = $this->member('man', 'woman', 'Christianity');
        $hindu = $this->member('man', 'woman', 'Hinduism');
        $jewish = $this->member('man', 'woman', 'Judaism');
        $none = $this->member('man', 'woman', 'None');

        $candidates = $this->candidatesFor($her);

        foreach ([$christian, $hindu, $jewish, $none] as $man) {
            $this->assertNotContains(
                $man->id,
                $candidates,
                "A Muslim woman was offered a {$man->religion} man. This is forbidden by "
                    .'consensus with no difference of opinion in any school, and it was the '
                    .'default behaviour of this platform before R6.',
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL, AND WITHOUT IT EVERY ASSERTION ABOVE IS WORTHLESS.
     *
     * An empty candidate set satisfies "she was not offered him" perfectly, and
     * an empty set is exactly what a broken filter produces. This proves the
     * permissible pairing IS still made.
     */
    public function test_a_muslim_woman_is_still_offered_a_muslim_man(): void
    {
        $her = $this->member('woman', 'man', 'Islam');
        $him = $this->member('man', 'woman', 'Islam');

        $this->assertContains(
            $him->id,
            $this->candidatesFor($her),
            'A Muslim woman is being offered nobody at all, so the exclusions above are '
                .'excluding everybody and this file is measuring an empty pool.',
        );
    }

    // ── The man's rule, which is wider but not unlimited ─────────────────

    public function test_a_muslim_man_may_be_offered_christian_and_jewish_women(): void
    {
        $him = $this->member('man', 'woman', 'Islam');

        $muslimah = $this->member('woman', 'man', 'Islam');
        $christian = $this->member('woman', 'man', 'Christianity');
        $jewish = $this->member('woman', 'man', 'Judaism');

        $candidates = $this->candidatesFor($him);

        foreach ([$muslimah, $christian, $jewish] as $woman) {
            $this->assertContains(
                $woman->id,
                $candidates,
                "A Muslim man was not offered a {$woman->religion} woman, which is permitted.",
            );
        }
    }

    public function test_a_muslim_man_is_not_offered_a_woman_of_another_faith(): void
    {
        $him = $this->member('man', 'woman', 'Islam');
        $hindu = $this->member('woman', 'man', 'Hinduism');

        $this->assertNotContains($hindu->id, $this->candidatesFor($him));
    }

    // ── ⚠️ The other direction, which a seeker-only reading would miss ───

    /**
     * ⚠️ THE HALF THE BRIEF DID NOT SPELL OUT, AND THE ONE THAT MATTERS MOST.
     *
     * The rule was written from the Muslim member's point of view. Applied only
     * to the seeker, the identical forbidden pairing is still reachable from the
     * other side: a Hindu man is seeking, his own faith restricts nobody, and
     * the engine hands him a Muslim woman. Same two people, same forbidden
     * introduction, arrived at by whoever the batch happened to be built for.
     */
    public function test_a_non_muslim_man_is_never_offered_a_muslim_woman(): void
    {
        $hinduMan = $this->member('man', 'woman', 'Hinduism');
        $muslimah = $this->member('woman', 'man', 'Islam');

        $this->assertNotContains(
            $muslimah->id,
            $this->candidatesFor($hinduMan),
            'A Muslim woman appeared in a non-Muslim man\'s candidate set. The pairing is '
                .'forbidden whichever of the two the engine is building a batch for.',
        );
    }

    public function test_a_non_muslim_man_is_still_offered_women_of_his_own_faith(): void
    {
        // ⚠️ Control for the assertion above: he must not lose everybody.
        $hinduMan = $this->member('man', 'woman', 'Hinduism');
        $hinduWoman = $this->member('woman', 'man', 'Hinduism');

        $this->assertContains($hinduWoman->id, $this->candidatesFor($hinduMan));
    }

    public function test_a_woman_of_another_faith_is_not_offered_a_muslim_man(): void
    {
        $hinduWoman = $this->member('woman', 'man', 'Hinduism');
        $muslimMan = $this->member('man', 'woman', 'Islam');

        $this->assertNotContains($muslimMan->id, $this->candidatesFor($hinduWoman));
    }

    public function test_a_christian_woman_may_be_offered_a_muslim_man(): void
    {
        $christian = $this->member('woman', 'man', 'Christianity');
        $muslimMan = $this->member('man', 'woman', 'Islam');

        $this->assertContains(
            $muslimMan->id,
            $this->candidatesFor($christian),
            'A Christian woman was not offered a Muslim man. That pairing is permitted from '
                .'his side and she carries no restriction of her own.',
        );
    }

    // ── Everybody else is untouched ──────────────────────────────────────

    public function test_two_members_of_another_faith_are_unaffected(): void
    {
        $man = $this->member('man', 'woman', 'Hinduism');
        $woman = $this->member('woman', 'man', 'Buddhism');

        $this->assertContains(
            $woman->id,
            $this->candidatesFor($man),
            'A faith rule about Muslim members changed the outcome for two people it does '
                .'not concern.',
        );
    }

    // ── ⚠️ The free-text legacy, which is how this rule gets defeated ────

    /**
     * ⚠️ THE COLUMN WAS AN OPEN TEXT BOX UNTIL R6, WITH "Islam" AS A
     * PLACEHOLDER RATHER THAN A CHOICE.
     *
     * So the rows already written contain whatever people typed. A comparison
     * against the literal string 'Islam' reads "muslim", "Sunni" and "Islam "
     * as not-Muslim and matches those women with non-Muslim men — this rule
     * defeated by a lowercase letter.
     */
    public function test_the_rule_survives_how_people_actually_typed_it(): void
    {
        foreach (['muslim', 'Muslim', 'ISLAM', ' islam ', 'Sunni'] as $written) {
            $her = $this->member('woman', 'man', $written);
            $christian = $this->member('man', 'woman', 'Christianity');

            $this->assertNotContains(
                $christian->id,
                $this->candidatesFor($her),
                "A woman who wrote her religion as \"{$written}\" was offered a Christian man. "
                    .'The filter is matching a literal string rather than a faith.',
            );
        }
    }

    // ── The rule object itself ───────────────────────────────────────────

    /**
     * ⚠️ SYMMETRY IS THE PROPERTY, SO IT IS ASSERTED DIRECTLY.
     *
     * Every pairing must give the same answer with the arguments swapped. If it
     * ever does not, the engine's answer depends on who it happened to be
     * building a batch for, which is precisely the bug this rule replaced.
     */
    public function test_the_rule_gives_the_same_answer_whichever_way_round(): void
    {
        $faiths = ['Islam', 'Christianity', 'Judaism', 'Hinduism', 'None'];

        foreach ($faiths as $a) {
            foreach ($faiths as $b) {
                $forward = FaithRule::permits($a, 'woman', $b, 'man');
                $backward = FaithRule::permits($b, 'man', $a, 'woman');

                $this->assertSame(
                    $forward,
                    $backward,
                    "A {$a} woman and a {$b} man get different answers depending on which of "
                        .'them the question is asked about.',
                );
            }
        }
    }

    public function test_an_unstated_religion_restricts_nobody(): void
    {
        /*
         * Instructed, and safe only because religion is REQUIRED to submit a
         * profile, and an unsubmitted profile is out of the pool by the first
         * condition of the query. The next test is what holds that shut.
         */
        $this->assertTrue(FaithRule::permits(null, 'woman', 'Hinduism', 'man'));
        $this->assertTrue(FaithRule::permits('Islam', 'woman', '', 'man'));
    }

    /**
     * ⚠️ THE GUARD ON THE PERMISSIVE BRANCH ABOVE.
     *
     * An unstated religion is treated as unrestricted. That is only acceptable
     * while it is unreachable — the moment `religion` stops being required to
     * submit a profile, a Muslim woman could be matched with anybody simply by
     * leaving the field blank.
     */
    public function test_religion_is_still_required_to_submit_a_profile(): void
    {
        $required = (new \ReflectionClass(\App\Services\Profile\ProfileCompleteness::class))
            ->getConstant('REQUIRED');

        $this->assertContains(
            'religion',
            $required['lifestyle'] ?? [],
            'Religion is no longer required to complete a profile, which makes the permissive '
                .'branch in FaithRule reachable: a Muslim woman could enter the pool with no '
                .'faith stated and be matched with anybody.',
        );
    }
}
