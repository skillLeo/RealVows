<?php

namespace App\Http\Controllers;

use App\Models\MatchRecord;
use App\Models\Report;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * ⚠️ The reported member is never told anything. Not that a report
     * exists, not who made it, not that it is being looked at. A reporting
     * system that leaks back to its subject invites retaliation against
     * somebody who did the right thing.
     */
    public function store(Request $request, MatchRecord $match): RedirectResponse
    {
        $profileId = $request->user()->profile?->id;

        // Only about somebody you were actually introduced to.
        abort_if($profileId === null, 404);
        abort_unless($match->seeker_profile_id === $profileId, 404);

        $data = $request->validate([
            'reason' => ['required', 'in:'.implode(',', array_keys(Report::REASONS))],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'reason.required' => 'Please choose what the concern is.',
        ]);

        try {
            Report::create([
                'reporter_profile_id' => $profileId,
                'subject_profile_id' => $match->matched_profile_id,
                'match_id' => $match->id,
                'reason' => $data['reason'],
                'note' => $data['note'] ?? null,
                'status' => Report::STATUS_OPEN,
            ]);
        } catch (UniqueConstraintViolationException) {
            /*
             * They have already reported this person and it is still open.
             * Told the same thing either way — a member should never have to
             * wonder whether their concern registered, and "you already did
             * that" reads as a rebuke for doing the right thing.
             */
        }

        return back()->with('success', 'Thank you. Someone will look at this.');
    }
}
