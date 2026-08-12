<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\GuardianDecision;
use App\Models\MatchRecord;
use App\Models\Photo;
use App\Services\Photos\PhotoStorageService;
use App\Support\GuardianPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The trusted contact's own screens.
 *
 * ⚠️ THEY SEE THEIR WARD AND NOTHING ELSE. No directory, no search, no other
 * members, no counts of anything platform-wide. Everything here starts from
 * the guardian's own row and cannot reach past it.
 *
 * ⚠️ EVERY PAYLOAD GOES THROUGH GuardianPresenter. That is the single emission
 * point where the appointed-guardian photograph rule is enforced, and this
 * controller must never assemble a payload itself.
 */
class GuardianDashboardController extends Controller
{
    public function __construct(private readonly GuardianPresenter $presenter) {}

    public function index(Request $request): Response
    {
        $guardian = $this->guardianFor($request);

        $pending = MatchRecord::query()
            ->where('seeker_profile_id', $guardian->member->profile?->id)
            ->with(['matchedProfile.photos'])
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (MatchRecord $match) => $this->presenter->introduction(
                $guardian,
                $match,
                $match->matchedProfile,
            ))
            ->all();

        return Inertia::render('Guardian/Dashboard', [
            'ward' => $this->presenter->ward($guardian, $guardian->member->profile),
            'introductions' => $pending,
            'isAppointed' => $guardian->is_appointed,
        ]);
    }

    /**
     * ⚠️ APPEND-ONLY. A changed mind writes a second row rather than editing
     * the first. The support flow that judges an unreasonable guardian reads
     * the whole sequence, and a mutable log cannot answer that honestly.
     */
    public function decide(Request $request, MatchRecord $match): RedirectResponse
    {
        $guardian = $this->guardianFor($request);

        abort_unless($match->seeker_profile_id === $guardian->member->profile?->id, 404);

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,declined'],
            'gate' => ['required', 'in:pool_entry,introduction,contact_exchange'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        GuardianDecision::create([
            'guardian_id' => $guardian->id,
            'match_id' => $match->id,
            'gate' => $validated['gate'],
            'decision' => $validated['decision'],
            'reason' => $validated['reason'] ?? null,
            'decided_at' => now(),
        ]);

        return back()->with('success', 'Thank you. Your decision has been recorded.');
    }

    /**
     * ⚠️ THE RULE IS RE-ASKED HERE, at the point the bytes would be streamed.
     *
     * The dashboard already withholds the URL from an appointed guardian, so in
     * normal use this is unreachable for them. It is asked again because a URL
     * that leaked into a browser history, a shared screenshot or a server log
     * must still be refused. A check that only runs where the link is drawn is
     * a check on the drawing, not on the access.
     */
    public function photo(Request $request, Photo $photo): StreamedResponse
    {
        $guardian = $this->guardianFor($request);

        abort_unless($this->presenter->mayStreamPhotograph($guardian), 404);

        $wardProfileId = $guardian->member->profile?->id;

        $reachable = $photo->profile_id === $wardProfileId
            || MatchRecord::where('seeker_profile_id', $wardProfileId)
                ->where('matched_profile_id', $photo->profile_id)
                ->exists();

        abort_unless($reachable, 404);

        return Storage::disk(PhotoStorageService::DISK)->response($photo->path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    private function guardianFor(Request $request): Guardian
    {
        $guardian = Guardian::where('user_id', $request->user()->id)
            ->whereNot('status', Guardian::STATUS_REPLACED)
            ->with('member.profile')
            ->first();

        abort_unless($guardian, 404);

        return $guardian;
    }
}
