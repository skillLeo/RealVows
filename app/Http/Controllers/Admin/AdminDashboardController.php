<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\Report;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Verification;
use App\Support\DashboardInsights;
use App\Support\FreePeriod;
use App\Support\ReviewerScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The staff overview.
 *
 * ⚠️ EVERY FIGURE IS SCOPED TO WHAT THE READER MAY ALREADY SEE.
 *
 * The admin is gender-separated: a female reviewer works female members only,
 * and that separation is the whole reason members agree to be looked at. An
 * unscoped dashboard would hand that reviewer a total member count, a signup
 * curve and a gender split — which is a description of the people they were
 * specifically not given access to. Aggregates leak; a count is information
 * about the rows behind it.
 *
 * So every query here runs through the same ReviewerScope the queues use.
 * A scoped reviewer sees their own half of the platform and is told so, rather
 * than being shown a number they cannot act on and might misread as theirs.
 *
 * ⚠️ NOTHING ON THIS PAGE IS INVENTED TO FILL SPACE.
 *
 * A dashboard is the easiest screen in any product to pad with plausible
 * charts. Every number below is counted from a table at request time. Where
 * there is genuinely nothing to show — no money during the free period — it
 * says so in a sentence instead of drawing an empty axis, because an empty
 * chart reads as a broken chart and invites somebody to go looking for the bug.
 */
class AdminDashboardController extends Controller
{
    /** Two weeks reads as a trend without pretending to be a business history. */
    private const TREND_DAYS = 14;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $genders = ReviewerScope::gendersFor($user);

        /*
         * ⚠️ null MEANS UNRESTRICTED, [] MEANS NOTHING — AND THEY MUST NOT BE
         * CONFLATED. `when($genders, …)` would treat an empty array as falsey
         * and quietly show a staff account with no reviewer role the entire
         * platform, which is the exact inversion of what the empty array is
         * for.
         */
        $scoped = fn ($query) => $genders === null
            ? $query
            : $query->whereIn('gender', $genders);

        $profiles = $scoped(Profile::query());

        $total = (clone $profiles)->count();
        $verified = (clone $profiles)->whereHas('verification', fn ($q) => $q->where('status', 'verified'))->count();
        $awaiting = (clone $profiles)->whereHas('verification', fn ($q) => $q->where('status', 'pending'))->count();
        $rejected = (clone $profiles)->whereHas('verification', fn ($q) => $q->where('status', 'rejected'))->count();

        // Somebody who has never submitted anything is a different problem from
        // somebody waiting on us, and lumping them together hides both.
        $unsubmitted = $total - $verified - $awaiting - $rejected;

        $profileIds = (clone $profiles)->pluck('id');

        $payload = [
            'scope' => [
                'restricted' => $genders !== null,
                'label' => $this->scopeLabel($genders),
            ],

            'members' => [
                'total' => $total,
                'verified' => $verified,
                'awaiting' => $awaiting,
                'rejected' => $rejected,
                'unsubmitted' => max(0, $unsubmitted),
                /*
                 * Signed in within thirty days. "Active" is a word that means
                 * whatever the person reading it assumes, so the definition
                 * travels with the number to the screen.
                 */
                'active' => User::whereHas('profile', fn ($q) => $scoped($q))
                    ->where('last_login_at', '>=', now()->subDays(30))
                    ->count(),
            ],

            'queues' => [
                'verifications' => Verification::where('status', 'pending')
                    ->whereIn('profile_id', $profileIds)
                    ->count(),
                'oldestWaitingDays' => $this->oldestWaitingDays($profileIds),
                /*
                 * ⚠️ NOT SCOPED BY GENDER, AND THAT IS CORRECT. A trusted
                 * contact is not a member and has no gender recorded. Scoping
                 * them by their ward's would be inventing a rule nobody set.
                 */
                'trustedContacts' => Guardian::where('status', 'submitted')->count(),
                'reports' => Report::where('status', 'open')->count(),
                'support' => SupportTicket::where('status', 'open')->count(),
            ],

            'introductions' => [
                'total' => MatchRecord::whereIn('seeker_profile_id', $profileIds)->count(),
                'delivered' => MatchRecord::whereIn('seeker_profile_id', $profileIds)->whereNotNull('delivered_at')->count(),
                'opened' => MatchRecord::whereIn('seeker_profile_id', $profileIds)->whereNotNull('opened_at')->count(),
                'unlocked' => MatchRecord::whereIn('seeker_profile_id', $profileIds)->where('status', 'unlocked')->count(),
            ],

            'signups' => $this->signupTrend($genders),
            'genderSplit' => $genders === null ? $this->genderSplit() : null,
            'completeness' => $this->completenessBands($profiles),

            /*
             * ⚠️ STATED, NOT DRAWN. While the free period is on there is no
             * revenue by design, and an empty money chart reads as a broken
             * money chart.
             */
            'freePeriod' => app(FreePeriod::class)->isActive(),

            /*
             * ⚠️ THE STAFF MEMBER'S OWN MEMBER-SIDE STATE, so /admin is a
             * complete landing place and nobody has to leave it for /dashboard.
             *
             * ⚠️ AND IT IS RENDERED ONLY WHEN IT SAYS SOMETHING. A reviewer
             * hired to check photographs has no profile of their own and no
             * introductions, so these panels would read "0% complete" and
             * "0 introductions" forever — two permanent zeroes on the first
             * screen they see every morning, which teaches them to ignore that
             * corner of the page. The client, who IS a member as well as the
             * owner, gets them because for him they are real.
             */
            'you' => $this->ownState($user),
        ];

        /*
         * ⚠️ DERIVED FROM THE PAYLOAD THAT WAS JUST BUILT, NOT FROM ITS OWN
         * QUERIES. Reading the database a second time would let the sentences
         * and the tiles disagree — "3 people waiting" printed beside a tile
         * showing 2, because a row landed between the two reads. The summary
         * must describe THIS page.
         */
        $payload['insights'] = DashboardInsights::from($payload);

        return Inertia::render('Admin/Dashboard', $payload);
    }

    /**
     * What this staff member has waiting on their own member account.
     *
     * ⚠️ null WHEN THERE IS NOTHING TO SAY, so the page can leave the panels
     * out rather than draw two permanent zeroes. A reviewer with no profile is
     * not a member with an empty one.
     *
     * @return array<string, mixed>|null
     */
    private function ownState(User $user): ?array
    {
        $profile = $user->profile;

        if (! $profile) {
            return null;
        }

        return [
            'completeness' => (int) ($profile->completeness_percent ?? 0),
            'credits' => $user->creditBalance(),
            'waiting' => MatchRecord::where('seeker_profile_id', $profile->id)
                ->whereNotNull('delivered_at')
                ->whereNull('opened_at')
                ->count(),
        ];
    }

    private function scopeLabel(?array $genders): string
    {
        if ($genders === null) {
            return 'Everyone on the platform';
        }

        if ($genders === []) {
            return 'Nothing is assigned to your account';
        }

        return 'Your review scope only: '.implode(' and ', array_map(
            fn (string $g) => $g === 'woman' ? 'women' : 'men',
            $genders,
        ));
    }

    private function oldestWaitingDays(\Illuminate\Support\Collection $profileIds): ?int
    {
        $oldest = Verification::where('status', 'pending')
            ->whereIn('profile_id', $profileIds)
            ->min('created_at');

        if (! $oldest) {
            return null;
        }

        /*
         * ⚠️ CAST EXPLICITLY. Carbon 3 returns a FLOAT from diffInDays — 0.102
         * for something submitted this morning — and returning that from a
         * `?int` method is an implicit narrowing PHP deprecates and will
         * eventually refuse. It also silently truncates, which is the correct
         * behaviour here and must therefore be the stated one.
         *
         * ⚠️ AND ON THE RIGHT WAY ROUND. Carbon's diff is signed. The oldest
         * row is in the past, so the past date is the receiver and now() the
         * argument. Reversed, this reports how many days until something that
         * already happened — as a negative. This project has already shipped a
         * rate-limit window of minus one day from exactly that mistake.
         */
        return (int) Carbon::parse($oldest)->diffInDays(now());
    }

    /** @return list<array{day: string, count: int}> */
    private function signupTrend(?array $genders): array
    {
        $rows = Profile::query()
            ->when($genders !== null, fn ($q) => $q->whereIn('gender', $genders))
            ->where('created_at', '>=', now()->subDays(self::TREND_DAYS - 1)->startOfDay())
            ->get(['created_at'])
            ->groupBy(fn ($p) => $p->created_at->toDateString())
            ->map->count();

        /*
         * ⚠️ EVERY DAY IS EMITTED, INCLUDING THE ZEROES. Grouping alone returns
         * only days that had a signup, and a chart drawn from that silently
         * closes the gaps — four signups across two weeks would render as four
         * consecutive busy days. The shape of the quiet is the information.
         */
        return collect(range(self::TREND_DAYS - 1, 0))
            ->map(function (int $ago) use ($rows) {
                $day = now()->subDays($ago)->toDateString();

                return ['day' => $day, 'count' => (int) ($rows[$day] ?? 0)];
            })
            ->all();
    }

    /** @return list<array{label: string, count: int}> */
    private function genderSplit(): array
    {
        return Profile::query()
            ->selectRaw('gender, count(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->map(fn (int $count, string $gender) => [
                'label' => match ($gender) {
                    'man' => 'Men',
                    'woman' => 'Women',
                    default => 'Not stated',
                },
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{label: string, count: int}> */
    private function completenessBands($profiles): array
    {
        $bands = [
            ['label' => 'Under 25%', 'min' => 0, 'max' => 24],
            ['label' => '25–49%', 'min' => 25, 'max' => 49],
            ['label' => '50–74%', 'min' => 50, 'max' => 74],
            ['label' => '75–99%', 'min' => 75, 'max' => 99],
            ['label' => 'Complete', 'min' => 100, 'max' => 100],
        ];

        return array_map(fn (array $band) => [
            'label' => $band['label'],
            'count' => (clone $profiles)
                ->whereBetween('completeness_percent', [$band['min'], $band['max']])
                ->count(),
        ], $bands);
    }
}
