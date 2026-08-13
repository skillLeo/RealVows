<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ⚠️ AUTHENTICATION BYPASS — REVIEW BUILDS ONLY.
 *
 * Signs in as a demo account without a password so the client can move
 * through the product quickly during review. He asked for these buttons and
 * uses them; they are not an oversight.
 *
 * Four things keep this from becoming a live hole:
 *   1. config('realvows.demo_logins') requires an explicit opt-in flag AND an
 *      APP_ENV of local or demo. Both, not either. (This paragraph used to say
 *      "local" alone, which was wrong — production runs as `demo` and a reader
 *      checking this file would have concluded the bypass was off when it was
 *      on.)
 *   2. The route is only registered when that config is true, so on a real
 *      production build the URL does not exist rather than being defended.
 *   3. Every action here re-checks, in case the route is ever registered
 *      unconditionally by mistake later.
 *   4. ⚠️ IT CLOSES ITSELF THE MOMENT A REAL PERSON REGISTERS. See
 *      `onlyDemoMembersExist()` below.
 *
 * It will only ever sign in to an @realvows.test address, so even if all of
 * the above failed it could not reach a genuine member's account.
 */
class DemoLoginController extends Controller
{
    private const DEMO_DOMAIN = '@realvows.test';

    /**
     * ⚠️ THE BYPASS DIES WHEN THE FIRST GENUINE MEMBER ARRIVES.
     *
     * The documented plan was that somebody changes APP_ENV to production on
     * launch day and the bypass closes. That is a plan that depends on a person
     * remembering a thing, at the busiest moment of the project, and the cost
     * of forgetting is not a broken page: one click on the login screen reaches
     * the admin panel, and the admin panel holds every member's photographs and
     * the identity documents they uploaded to be verified.
     *
     * Demo data makes that harmless. A real member makes it a disclosure of the
     * most sensitive material this product holds.
     *
     * So the condition is no longer a flag anybody has to set. Any account
     * whose address is not @realvows.test means real people are here, and the
     * one-click buttons stop existing on their own — before the flag is
     * changed, before anybody notices, at exactly the right moment.
     *
     * ⚠️ THIS INCLUDES THE CLIENT'S OWN ACCOUNT. The day Umar registers a
     * genuine admin address the demo buttons disappear for good, and he signs
     * in with a password from then on. That is the intended behaviour and he
     * has to be told, because otherwise it reads as a fault.
     *
     * One `exists()` query on the login page. Deliberately NOT cached: the
     * cache store on this host is the database, so caching it would buy no
     * query and would add a window in which the bypass outlived the condition
     * that was meant to close it.
     */
    public static function onlyDemoMembersExist(): bool
    {
        return ! User::where('email', 'not like', '%'.self::DEMO_DOMAIN)->exists();
    }

    private static function available(): bool
    {
        return (bool) config('realvows.demo_logins') && self::onlyDemoMembersExist();
    }

    public function __invoke(Request $request, string $account): RedirectResponse
    {
        abort_unless(self::available(), 404);

        $email = str_contains($account, '@') ? $account : $account.self::DEMO_DOMAIN;

        abort_unless(str_ends_with($email, self::DEMO_DOMAIN), 404);

        $user = User::where('email', $email)->first();

        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['email' => "No demo account for {$email}. Run: php artisan realvows:demo"]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->route($user->isAdmin() ? 'admin.verifications' : 'dashboard');
    }

    /**
     * The buttons the login screen renders. Kept here rather than in the
     * controller that builds the login page, so everything about the bypass
     * lives in one file.
     *
     * @return array<int, array<string, string>>
     */
    public static function buttons(): array
    {
        if (! self::available()) {
            return [];
        }

        $accounts = [
            ['key' => 'admin', 'label' => 'Admin', 'group' => 'Staff'],
            ['key' => 'reviewer', 'label' => 'Reviewer', 'group' => 'Staff'],
        ];

        foreach (range(1, 6) as $i) {
            $accounts[] = ['key' => "boy{$i}", 'label' => "Boy {$i}", 'group' => 'Men'];
        }

        foreach (range(1, 6) as $i) {
            $accounts[] = ['key' => "girl{$i}", 'label' => "Girl {$i}", 'group' => 'Women'];
        }

        return $accounts;
    }
}
