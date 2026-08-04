<?php

namespace App\Http\Controllers;

use App\Models\MatchRecord;
use App\Support\MatchPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every introduction this member has ever been given, in one place.
 *
 * ⚠️ FILTERING BY STATE IS NAVIGATION. SEARCHING BY ATTRIBUTE IS BROWSING.
 * BROWSING IS BANNED, AND THIS IS THE SCREEN WHERE IT WOULD CREEP BACK IN.
 *
 * The distinction is not stylistic. A state filter reorganises rows the engine
 * already chose for this one member — it cannot surface a person the engine did
 * not select. An attribute filter ("show me women in Kingston under 30") turns
 * the same screen into a directory, which is the one thing the product promises
 * never to be.
 *
 * So the ONLY accepted parameter is `state`, validated against a closed list.
 * Anything else in the query string is ignored rather than passed through, and
 * InboxTest asserts that adding `?city=Kingston` changes nothing.
 *
 * ⚠️ There is no query here that is not scoped to this member's profile id.
 * Not "scoped by default" — scoped, with no branch that omits it.
 */
class InboxController extends Controller
{
    /**
     * The five states a member can meaningfully sort their introductions into.
     *
     * ⚠️ These are presentation groupings over the two underlying columns, not
     * a third state machine. `status` is access and `response` is reaction, and
     * collapsing them here would be the enum-with-too-many-values mistake the
     * Phase 2 reshape already avoided in the schema.
     */
    public const STATES = ['all', 'waiting', 'open', 'interested', 'passed', 'closed'];

    public function __construct(private readonly MatchPresenter $presenter) {}

    public function index(Request $request): Response
    {
        $profile = $request->user()->loadMissing('profile')->profile;

        $state = $request->query('state');
        $state = in_array($state, self::STATES, true) ? $state : 'all';

        $matches = $profile
            ? $this->query($profile->id, $state)->with(['matched.photos'])->limit(60)->get()
            : collect();

        return Inertia::render('Inbox', [
            'state' => $state,
            'states' => $this->counts($profile?->id),
            'matches' => $matches->map(fn (MatchRecord $m) => $this->presenter->forCard($m))->all(),
        ]);
    }

    /**
     * ⚠️ NEWEST FIRST, UNLIKE THE DASHBOARD.
     *
     * The dashboard sorts unlocked-first by score, because it is answering
     * "what should I look at now?". The inbox is a record and answers "what
     * happened, and when?" — so it is chronological, and reordering it by score
     * would make an old introduction resurface above a new one.
     */
    private function query(int $profileId, string $state)
    {
        $query = MatchRecord::query()
            ->where('seeker_profile_id', $profileId)
            ->orderByDesc('generated_at')
            ->orderByDesc('id');

        return match ($state) {
            'waiting' => $query->where('status', MatchRecord::STATUS_TEASER),
            'open' => $query->where('status', MatchRecord::STATUS_UNLOCKED)->whereNull('response'),
            'interested' => $query->where('response', MatchRecord::RESPONSE_INTERESTED),
            'passed' => $query->where('response', MatchRecord::RESPONSE_PASSED),
            'closed' => $query->where('status', MatchRecord::STATUS_EXPIRED),
            default => $query,
        };
    }

    /**
     * How many are in each state, so the tabs mean something.
     *
     * ⚠️ Counted with one grouped query rather than six. On shared hosting the
     * database is the slowest thing we touch, and six counts on every inbox
     * load is the kind of cost that is invisible until thirty people are using
     * the product at once.
     *
     * @return array<string, int>
     */
    private function counts(?int $profileId): array
    {
        $counts = array_fill_keys(self::STATES, 0);

        if (! $profileId) {
            return $counts;
        }

        $rows = MatchRecord::query()
            ->where('seeker_profile_id', $profileId)
            ->selectRaw('status, response, count(*) as total')
            ->groupBy('status', 'response')
            ->get();

        foreach ($rows as $row) {
            $counts['all'] += $row->total;

            if ($row->status === MatchRecord::STATUS_EXPIRED) {
                $counts['closed'] += $row->total;

                continue;
            }

            if ($row->response === MatchRecord::RESPONSE_INTERESTED) {
                $counts['interested'] += $row->total;
            } elseif ($row->response === MatchRecord::RESPONSE_PASSED) {
                $counts['passed'] += $row->total;
            } elseif ($row->status === MatchRecord::STATUS_UNLOCKED) {
                $counts['open'] += $row->total;
            } else {
                $counts['waiting'] += $row->total;
            }
        }

        return $counts;
    }
}
