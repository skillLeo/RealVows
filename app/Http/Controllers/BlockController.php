<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\MatchRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A member deciding never to be shown another.
 *
 * ⚠️ SILENT TO THE BLOCKED PARTY, AND THE RESPONSE SHAPE PROVES IT.
 *
 * Nothing notifies them, nothing changes on their side that they could notice,
 * and their experience is that this person simply never appears again — which
 * is indistinguishable from the engine not having matched them, the normal
 * experience of almost everybody on the platform.
 *
 * ⚠️ BLOCKING IS NOT REPORTING. Reporting asks a human to look at somebody;
 * blocking asks the engine to forget them. A member who is frightened should
 * be able to do the second instantly without waiting on the first, so this
 * route does not create a report and does not require a reason.
 */
class BlockController extends Controller
{
    public function store(Request $request, MatchRecord $match): RedirectResponse
    {
        $profileId = $request->user()->profile?->id;

        abort_if($profileId === null, 404);

        /*
         * ⚠️ Resolved from the signed-in member, never the URL. Without this,
         * the route is a way to block arbitrary members on somebody else's
         * behalf — which would let one account quietly empty another's pool.
         */
        abort_unless($match->seeker_profile_id === $profileId, 404);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        Block::firstOrCreate(
            [
                'blocker_profile_id' => $profileId,
                'blocked_profile_id' => $match->matched_profile_id,
            ],
            ['reason' => $validated['reason'] ?? null],
        );

        /*
         * The existing introduction is expired rather than deleted. Deleting it
         * would remove the ledger's explanation of where a spent credit went,
         * and a member who blocks somebody they paid to meet still deserves the
         * record of having paid.
         */
        $match->forceFill(['status' => MatchRecord::STATUS_EXPIRED])->save();

        return redirect()->route('dashboard')->with(
            'success',
            'You will not be shown this person again.',
        );
    }
}
