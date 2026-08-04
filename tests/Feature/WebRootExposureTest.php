<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ⚠️ THIS EXISTS BECAUSE IT ALREADY HAPPENED. 2 August 2026.
 *
 * On this host the entire Laravel application lives INSIDE the served
 * directory, so `.env`, `storage/` and every member photograph sit in the
 * folder the web server is willing to hand out. One `.htaccess` at the web
 * root is the only thing standing in front of them.
 *
 * That file was hand-written on the server during setup and never committed.
 * The deploy runs `rsync -az --delete`, which removes whatever the repository
 * does not have — so a deploy deleted it. For the duration of that window
 * `composer.json` was publicly readable and application PHP was being
 * executed directly by the web server. `.env` survived only because LiteSpeed
 * blocks dotfiles by default, which is luck rather than design.
 *
 * The deploy reported success. The only thing that failed was a health check
 * looking for HTTP 200 on the home page, and it could not tell the difference
 * between "the site is broken" and "the site is serving you its source code".
 *
 * ⚠️ THIS RUNS AGAINST THE LIVE DOMAIN, AFTER DEPLOY, AND FAILS THE DEPLOY.
 *
 * Checking the repository proves nothing here: the file was correct in the
 * repository and absent on the server. Only an HTTP request to the real host
 * can answer the question, so this test is skipped unless DEPLOY_URL is set,
 * and the deploy sets it.
 */
class WebRootExposureTest extends TestCase
{
    /**
     * ⚠️ EVERY ONE OF THESE WAS REACHABLE, OR NEARLY SO, DURING THE INCIDENT.
     *
     * `.env` is first because it is the whole game: database password, app
     * key, and the payment gateway credentials once they are live.
     */
    private const MUST_NOT_BE_READABLE = [
        '.env',
        '.env.example',
        'composer.json',
        'composer.lock',
        'package.json',
        'artisan',
        'storage/logs/laravel.log',
        // Redacted, but it still records who messaged us and when.
        'storage/logs/whatsapp-webhook.log',
        'app/Models/User.php',
        'config/database.php',
        '.git/config',
        '.git/HEAD',
        'vendor/autoload.php',
        'database/seeders/DemoSeeder.php',
    ];

    private function base(): string
    {
        $url = env('DEPLOY_URL');

        if (! $url) {
            $this->markTestSkipped(
                'DEPLOY_URL is not set. This check only means anything against the live host — '
                    .'the file that failed was correct in the repository and missing on the server.'
            );
        }

        return rtrim($url, '/');
    }

    /**
     * ⚠️ NOT `status()`. PHPUnit\Framework\TestCase declares `status()` as
     * FINAL, and overriding it is a fatal error at class-load time — which
     * takes down the whole suite, not just this file, and reports as a silent
     * exit 255 with no output at all.
     */
    private function statusFor(string $path): int
    {
        /*
         * ⚠️ RESOLVED BEFORE THE try, NOT INSIDE IT.
         *
         * `base()` calls markTestSkipped, which works by throwing — and a
         * `catch (\Throwable)` around it swallows PHPUnit's own control flow
         * and turns "skipped, because there is no host to check" into
         * "failed, could not reach the host". The suite then reports a
         * security failure on every developer machine.
         */
        $url = $this->base().'/'.ltrim($path, '/');

        try {
            return Http::withoutRedirecting()
                ->timeout(20)
                ->withHeaders(['User-Agent' => 'RealVows-deploy-check'])
                ->get($url)
                ->status();
        } catch (\Throwable $e) {
            $this->fail("Could not reach {$path}: ".$e->getMessage());
        }
    }

    /** ⚠️ THE CHECK THIS CLASS EXISTS FOR. */
    public function test_no_application_file_is_readable_over_http(): void
    {
        $exposed = [];

        foreach (self::MUST_NOT_BE_READABLE as $path) {
            $status = $this->statusFor($path);

            /*
             * 404 and 403 both mean "you cannot have it". 404 is what this
             * host returns, because the web-root rewrite sends the request
             * into public/ where the file does not exist — and 404 is the
             * better answer, since 403 confirms the file is there.
             *
             * Anything in the 200 range means the server handed it over. A
             * 500 is also a failure: it means PHP EXECUTED the file, which is
             * the web server treating application source as a script.
             */
            if ($status < 400 || $status >= 500) {
                $exposed[] = "/{$path} answered {$status}";
            }
        }

        $this->assertSame(
            [],
            $exposed,
            "The web root is exposing application files:\n  ".implode("\n  ", $exposed)
                ."\n\nThis is almost certainly the root .htaccess missing or overwritten. It is "
                .'tracked in the repository — check it survived the deploy.'
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL, AND RULE 5 IN ITS PUREST FORM.
     *
     * Every assertion above passes if the site is completely unreachable —
     * a DNS failure, a firewall, a typo'd DEPLOY_URL would all produce a wall
     * of 404s and a green test reporting the web root is perfectly secure.
     *
     * So the same reader must be shown returning 200 for something that is
     * genuinely meant to be public. If the home page does not answer, this
     * test knows nothing and says so.
     */
    public function test_the_reader_can_tell_a_readable_file_from_a_blocked_one(): void
    {
        $this->assertSame(
            200,
            $this->statusFor('/'),
            'The home page did not answer 200, so every other assertion in this class is '
                .'meaningless — an unreachable site returns 404 for the secrets too.'
        );

        // And a public asset really is served, so "404 everywhere" cannot pass.
        $this->assertLessThan(
            400,
            $this->statusFor('robots.txt'),
            'Even robots.txt is unreachable. This check cannot distinguish a locked-down web '
                .'root from a site that is simply down.'
        );
    }

    /**
     * ⚠️ The guard itself must be in the repository, or the next
     * `rsync --delete` removes it exactly as it did the first time.
     */
    public function test_the_web_root_guard_is_tracked_in_the_repository(): void
    {
        $htaccess = base_path('.htaccess');

        $this->assertFileExists(
            $htaccess,
            'The web-root .htaccess is not in the repository. rsync --delete removes anything '
                .'the repository does not have, which is exactly how the site went down.'
        );

        $contents = file_get_contents($htaccess);

        $this->assertStringContainsString(
            'RewriteRule ^(.*)$ public/$1',
            $contents,
            'The rewrite into public/ has gone. Without it the server hands out the application '
                .'directory instead of the front controller.'
        );

        // Denied twice, through two different modules, so disabling one does
        // not open the doors.
        $this->assertStringContainsString('mod_authz_core.c', $contents);
        $this->assertStringContainsString('mod_access_compat.c', $contents);

        // And it must not have been excluded from the deploy again.
        $workflow = file_get_contents(base_path('.github/workflows/deploy.yml'));

        $this->assertStringNotContainsString(
            "--exclude '.htaccess'",
            $workflow,
            'The web-root guard has been excluded from rsync. An excluded file drifts silently, '
                .'which is the situation that caused the outage — it must be synced from the '
                .'repository every time.'
        );
    }
}
