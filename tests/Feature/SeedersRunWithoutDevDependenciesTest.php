<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A seeder that runs on the server must not need a development dependency.
 *
 * ⚠️ THE FAILURE IS INVISIBLE UNTIL THE ONE MACHINE THAT MATTERS.
 *
 * Production installs with `composer install --no-dev`, so fakerphp/faker is
 * not there. Every model factory calls `fake()`, so a single `Model::factory()`
 * in a seeder runs perfectly on a laptop and dies on the VPS with
 * "Call to undefined function Database\Factories\fake()".
 *
 * I did exactly that: added guardian accounts with `User::factory()->create()`
 * while every other account in the same file used `User::create()`. It seeded
 * thirty members locally and refused to run on the server, which is the only
 * place the demo data is actually looked at.
 *
 * ⚠️ THE WHOLE SUITE CANNOT CATCH THIS ON ITS OWN. Tests install WITH dev
 * dependencies, so `fake()` is always defined here and the seeder always
 * works. The suite is running in the one environment where the bug cannot
 * reproduce, which is why this reads the source instead.
 */
class SeedersRunWithoutDevDependenciesTest extends TestCase
{
    /**
     * Seeders that are run on the server, by the deploy workflow or by hand.
     *
     * ⚠️ NOT EVERY SEEDER. A test-only seeder may use factories freely — that
     * is what they are for. This is the list that has to survive --no-dev.
     */
    private const RUNS_ON_PRODUCTION = [
        'DemoSeeder.php',
        'SettingsSeeder.php',
        'RolesAndPermissionsSeeder.php',
    ];

    public function test_no_production_seeder_calls_a_model_factory(): void
    {
        $offenders = [];
        $checked = 0;

        foreach (self::RUNS_ON_PRODUCTION as $name) {
            $path = database_path('seeders/'.$name);

            $this->assertFileExists($path, "{$name} is on the production list and does not exist.");

            $source = File::get($path);

            // Comments discuss the very call being searched for — this file's
            // own fix is described in one — so they are removed first.
            $source = preg_replace('#/\*.*?\*/#s', '', $source);
            $source = preg_replace('#//.*$#m', '', (string) $source);

            $checked++;

            if (preg_match('/\b[A-Z]\w*::factory\s*\(/', (string) $source, $m)) {
                $offenders[] = "{$name} → {$m[0]}";
            }
        }

        /*
         * ⚠️ PROVE THE FILES WERE READ. Every assertion here is about absence,
         * and a wrong path reports a clean result just as loudly as clean code.
         */
        $this->assertSame(count(self::RUNS_ON_PRODUCTION), $checked);

        $this->assertSame([], $offenders, implode("\n", [
            'These seeders run on the server, where composer install --no-dev means',
            'fakerphp/faker is absent and every factory dies with',
            '"Call to undefined function fake()". Use Model::create() instead.',
        ]));
    }

    public function test_the_reader_can_still_see_a_factory_call(): void
    {
        /*
         * ⚠️ POSITIVE CONTROL. The assertion above is satisfied by a regex that
         * has quietly stopped matching, and nothing else in the suite can catch
         * this class of fault — tests run WITH dev dependencies, so the bug
         * cannot reproduce in the environment doing the checking.
         */
        $broken = '<?php $account = User::factory()->create(["email" => "x@y.test"]);';

        $this->assertMatchesRegularExpression('/\b[A-Z]\w*::factory\s*\(/', $broken);

        $fixed = '<?php $account = User::create(["email" => "x@y.test"]);';

        $this->assertDoesNotMatchRegularExpression('/\b[A-Z]\w*::factory\s*\(/', $fixed);
    }
}
