<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ⚠️ AUTHENTICATION BYPASS — LOCAL ONLY.
 *
 * Signs in as a demo account without a password so the client can move
 * through the product quickly during review.
 *
 * Three things keep this from ever becoming a live hole:
 *   1. config('realvows.demo_logins') requires APP_ENV=local AND an explicit
 *      opt-in flag. Both, not either.
 *   2. The route is only registered when that config is true, so on staging
 *      or production the URL does not exist rather than being defended.
 *   3. Every action here re-checks, in case the route is ever registered
 *      unconditionally by mistake later.
 *
 * It will only ever sign in to an @realvows.test address, so even if all of
 * the above failed it could not reach a genuine member's account.
 */
class DemoLoginController extends Controller
{
    private const DEMO_DOMAIN = '@realvows.test';

    public function __invoke(Request $request, string $account): RedirectResponse
    {
        abort_unless(config('realvows.demo_logins'), 404);

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
        if (! config('realvows.demo_logins')) {
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
