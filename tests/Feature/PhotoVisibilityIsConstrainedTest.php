<?php

namespace Tests\Feature;

use App\Support\PhotoVisibilityPolicy;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ A CONSTRAINT THAT OUTLIVES THE VALUE CURRENTLY SET.
 *
 * The scholar was asked whether a guardian may see a woman's photograph in
 * order to show it to a suitor. His answer decided two things at once:
 *
 *  · Looking is "not religiously necessary. If only the women of the household
 *    see her, that is also sufficient." So `never` is religiously safe, the
 *    product is complete without viewing, and nothing has to change today.
 *
 *  · "It should be clear that permission is for ONE GLANCE. Meeting in
 *    seclusion, or demanding to look REPEATEDLY, and also arranging
 *    conversation between the two, is not permitted in Shariah."
 *
 * ⚠️ THE SECOND HALF IS THE ONE THAT GETS LOST, because it constrains a change
 * nobody is making yet. Somebody, at some point, will want to relax this
 * setting. The obvious way to do that is to flip the value and let the existing
 * photograph route serve the file — and that produces a stored image a member
 * can reopen whenever they like, which is repeated looking with extra steps.
 *
 * ⚠️ SO THIS TEST EXISTS TO FAIL AT THAT MOMENT. It does not stop anybody
 * relaxing the setting. It stops them doing it without reading the constraint
 * first, which is the only failure mode a rule this quiet actually has.
 */
class PhotoVisibilityIsConstrainedTest extends TestCase
{
    /**
     * ⚠️ THE SHIPPED DEFAULT, READ FROM THE SEEDER RATHER THAN ASSUMED.
     *
     * The scholar's answer means this needs no defending: viewing is better but
     * not necessary, and the women of the household seeing her is sufficient on
     * its own. What matters is that a fresh install starts closed rather than
     * open, because the safe direction here is the one nobody has to notice.
     */
    public function test_a_fresh_install_starts_closed(): void
    {
        $seeder = File::get(base_path('database/seeders/SettingsSeeder.php'));

        $this->assertMatchesRegularExpression(
            '/photo_visibility.*never/s',
            $seeder,
            'A new installation no longer defaults photograph visibility to `never`. The '
                .'default is what every install that nobody configures will run on.',
        );
    }

    /**
     * ⚠️ THE TRIPWIRE. Adding a value here fails this test, and the only way to
     * make it pass is to come and read what the constraint requires.
     */
    public function test_no_new_visibility_value_appears_without_the_constraint_being_read(): void
    {
        $this->assertSame(
            [
                PhotoVisibilityPolicy::NEVER,
                PhotoVisibilityPolicy::AFTER_CONTACT_APPROVAL,
                PhotoVisibilityPolicy::AT_MUTUAL_INTEREST,
            ],
            PhotoVisibilityPolicy::VALUES,
            "A photograph visibility value has been added, changed or removed.\n\n"
                ."The scholar's ruling constrains this permanently: permission is for ONE "
                ."GLANCE, and repeated looking is not permitted.\n\n"
                .'So a new value may not mean a stored image a member can reopen at will. It '
                .'has to be a single view, shown once and then gone. If that is what you have '
                .'built, update this list and say so here. If it is not, the value is not ready.',
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL FOR THE TRIPWIRE ABOVE.
     *
     * A comparison against a constant that had been deleted, renamed or emptied
     * would pass just as happily as one against a correct list. This proves the
     * three values are really there and really distinct, so the assertion is
     * measuring something.
     */
    public function test_the_three_values_exist_and_are_distinct(): void
    {
        $this->assertCount(3, array_unique(PhotoVisibilityPolicy::VALUES));

        foreach (PhotoVisibilityPolicy::VALUES as $value) {
            $this->assertIsString($value);
            $this->assertNotSame('', $value);
        }
    }

    /**
     * ⚠️ THE REASONING HAS TO TRAVEL WITH THE CODE.
     *
     * A test that fails with a good message is still only read by whoever runs
     * it. Somebody reading the policy itself must find the constraint there,
     * because that is where they will be when they decide to change it.
     */
    public function test_the_constraint_is_written_where_somebody_changing_it_will_look(): void
    {
        $source = File::get(base_path('app/Support/PhotoVisibilityPolicy.php'));

        $this->assertStringContainsString(
            'ONE GLANCE',
            $source,
            'The scholar\'s constraint is no longer documented in the policy itself, so the '
                .'next person to relax this setting has no way to know it exists.',
        );

        $this->assertStringContainsString('DO NOT "HARDEN" THIS', $source);
    }
}
