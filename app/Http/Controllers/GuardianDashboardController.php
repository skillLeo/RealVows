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

    /**
     * The joint photograph: the primary check in the whole system.
     *
     * ⚠️ NO GOVERNMENT ID IS COLLECTED, HERE OR ANYWHERE. A passport proves who
     * somebody is and says nothing about whether this man is that woman's
     * father, which is the only claim that matters — and Caribbean documents
     * largely cannot establish it. Collecting them would build the most
     * sensitive store on the platform in exchange for evidence of the wrong
     * thing.
     *
     * ⚠️ THE CODE IS ISSUED ONLY AFTER THEY HAVE SIGNED IN, and that ordering
     * is the check. A photograph of two people holding a code generated minutes
     * ago cannot be an old picture found on a phone, and the person holding it
     * had to receive the invitation and set their own password to see it.
     */
    public function joint(Request $request): Response
    {
        $guardian = $this->guardianFor($request);

        // Issued once and kept, so a refresh does not invalidate a photograph
        // somebody is in the middle of taking.
        if (! $guardian->joint_photo_code) {
            $guardian->forceFill([
                'joint_photo_code' => strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)),
            ])->save();
        }

        return Inertia::render('Guardian/JointPhoto', [
            'code' => $guardian->fresh()->joint_photo_code,
            'memberName' => $guardian->member?->profile?->first_name,
            'uploaded' => $guardian->joint_photo_uploaded_at !== null,
            'status' => $guardian->status,
        ]);
    }

    public function storeJoint(Request $request): RedirectResponse
    {
        $guardian = $this->guardianFor($request);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
        ]);

        /*
         * ⚠️ PRIVATE DISK. This is a photograph of two identifiable people and
         * it must never be reachable without going through a controller that
         * asks who is looking. Same disk and same rule as member photographs.
         */
        $path = $request->file('photo')->store(
            'guardians/'.$guardian->id,
            PhotoStorageService::DISK,
        );

        $guardian->forceFill([
            'joint_photo_path' => $path,
            'joint_photo_uploaded_at' => now(),
            'status' => Guardian::STATUS_SUBMITTED,
        ])->save();

        return back()->with('success', 'Thank you. Someone will check this and let you know.');
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
