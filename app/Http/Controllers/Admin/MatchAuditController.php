<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MatchRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ⚠️ THE ONLY PLACE THE SIX COMPONENT SCORES ARE EVER VISIBLE, and only to
 * staff holding matches.view.
 *
 * A member asking "why 87?" gets an answer from here, given to them by a
 * person — never by a screen that would leak the mechanism.
 */
class MatchAuditController extends Controller
{
    public function index(Request $request): Response
    {
        $matches = MatchRecord::query()
            ->with(['seeker:id,first_name,city', 'matched:id,first_name,city'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when(
                $request->string('sort')->toString() === 'date',
                fn ($q) => $q->latest('generated_at'),
                fn ($q) => $q->orderByDesc('total_score')
            )
            ->paginate(30)
            ->withQueryString()
            ->through(fn (MatchRecord $m) => [
                'id' => $m->id,
                'seeker' => $m->seeker?->first_name,
                'matched' => $m->matched?->first_name,
                'score' => (float) $m->total_score,
                'status' => $m->status,
                'response' => $m->response,
                'generatedAt' => $m->generated_at?->format('j M Y'),
                'unlockedAt' => $m->unlocked_at?->format('j M Y'),
            ]);

        return Inertia::render('Admin/Matches/Index', [
            'matches' => $matches,
            'filters' => [
                'status' => $request->string('status')->toString(),
                'sort' => $request->string('sort')->toString() ?: 'score',
            ],
            'counts' => [
                'all' => MatchRecord::count(),
                'teaser' => MatchRecord::where('status', MatchRecord::STATUS_TEASER)->count(),
                'unlocked' => MatchRecord::where('status', MatchRecord::STATUS_UNLOCKED)->count(),
                'expired' => MatchRecord::where('status', MatchRecord::STATUS_EXPIRED)->count(),
            ],
        ]);
    }

    public function show(MatchRecord $match): Response
    {
        $match->load(['seeker', 'matched', 'ledgerEntry']);

        $breakdown = $match->score_breakdown ?? [];

        return Inertia::render('Admin/Matches/Show', [
            'match' => [
                'id' => $match->id,
                'score' => (float) $match->total_score,
                'status' => $match->status,
                'response' => $match->response,
                'rationale' => $match->compatibility_rationale,
                'commonalities' => $match->commonalities ?? [],
                'timeline' => array_values(array_filter([
                    $match->generated_at ? ['label' => 'Generated', 'at' => $match->generated_at->format('j M Y, H:i')] : null,
                    $match->delivered_at ? ['label' => 'Delivered', 'at' => $match->delivered_at->format('j M Y, H:i')] : null,
                    $match->unlocked_at ? ['label' => 'Opened', 'at' => $match->unlocked_at->format('j M Y, H:i')] : null,
                    $match->viewed_at ? ['label' => 'Viewed', 'at' => $match->viewed_at->format('j M Y, H:i')] : null,
                    $match->responded_at ? ['label' => 'Responded', 'at' => $match->responded_at->format('j M Y, H:i')] : null,
                ])),
                'paidWithLedgerId' => $match->unlocked_by_ledger_id,
            ],

            'seeker' => $this->side($match->seeker),
            'matched' => $this->side($match->matched),

            /*
             * The full working, including which components had no data and
             * how their weight was redistributed — so a score built from four
             * components can be explained rather than merely reported.
             */
            'components' => $breakdown['components'] ?? [],
            'redistributed' => $breakdown['redistributed'] ?? [],
            'missing' => $breakdown['missing'] ?? [],
        ]);
    }

    private function side(?\App\Models\Profile $profile): ?array
    {
        if (! $profile) {
            return null;
        }

        return [
            'id' => $profile->id,
            'name' => trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')),
            'age' => $profile->age(),
            'city' => $profile->city,
            'country' => $profile->country,
            'occupation' => $profile->occupation,
            'religion' => $profile->religion,
            'smoking' => $profile->smoking,
            'drinking' => $profile->drinking,
            'wantsChildren' => $profile->wants_children,
            'timeline' => $profile->marriage_timeline,
            'coreValues' => $profile->core_values ?? [],
            'interests' => $profile->interests ?? [],
        ];
    }
}
