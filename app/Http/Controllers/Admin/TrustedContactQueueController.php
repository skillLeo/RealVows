<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Services\Photos\PhotoStorageService;
use App\Support\ReviewerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The staff queue for trusted contacts.
 *
 * ⚠️ TWO QUEUES, ONE SCREEN. Contacts who have submitted a joint photograph
 * and are waiting for a human, and members who told us they have nobody and are
 * waiting for us to arrange somebody. They are the same job — deciding whether
 * a member can safely be matched — and splitting them into two screens is how
 * the second one gets forgotten.
 *
 * ⚠️ GENDER-SEPARATED, SAME AS VERIFICATION. The joint photograph shows the
 * member. A male reviewer must not be able to reach a female member's joint
 * photograph any more than her verification photograph, and it is enforced in
 * the query rather than the view for the same reason.
 */
class TrustedContactQueueController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $awaiting = Guardian::query()
            ->where('status', Guardian::STATUS_SUBMITTED)
            ->where('is_appointed', false)
            ->with('member.profile')
            ->get()
            ->filter(fn (Guardian $g) => ReviewerScope::maySee($user, $g->member?->profile?->gender))
            ->map(fn (Guardian $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'relationship' => Guardian::RELATIONSHIPS[$g->relationship] ?? $g->relationship,
                'memberFirstName' => $g->member?->profile?->first_name,
                'code' => $g->joint_photo_code,
                'photoUrl' => $g->joint_photo_path ? route('admin.trusted-contacts.photo', $g->id) : null,
                'flags' => $g->risk_reasons ?? [],
                'submittedAt' => $g->joint_photo_uploaded_at?->format('j F Y'),
            ])
            ->values();

        /*
         * ⚠️ THE NO-CONTACT QUEUE. A member who told us she has nobody is
         * waiting on us, not on anybody else, and nothing else in the product
         * will surface her. Left off this screen she waits indefinitely with no
         * error anywhere.
         */
        $needingAppointment = Guardian::query()
            ->where('is_appointed', true)
            ->whereNull('user_id')
            ->whereNot('status', Guardian::STATUS_REPLACED)
            ->with('member.profile')
            ->get()
            ->filter(fn (Guardian $g) => ReviewerScope::maySee($user, $g->member?->profile?->gender))
            ->map(fn (Guardian $g) => [
                'id' => $g->id,
                'memberFirstName' => $g->member?->profile?->first_name,
                'waitingSince' => $g->created_at->format('j F Y'),
                'daysWaiting' => (int) $g->created_at->diffInDays(now()),
            ])
            ->values();

        return Inertia::render('Admin/TrustedContacts/Index', [
            'awaiting' => $awaiting,
            'needingAppointment' => $needingAppointment,
        ]);
    }

    public function review(Request $request, Guardian $guardian): RedirectResponse
    {
        $this->assertInScope($request, $guardian);

        $validated = $request->validate([
            'decision' => ['required', 'in:verified,rejected'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        /*
         * ⚠️ A REJECTION MUST CARRY A REASON. The member is told her trusted
         * contact could not be confirmed and needs to know whether to fix
         * something or name somebody else. "Rejected" with no reason is a dead
         * end she cannot act on.
         */
        if ($validated['decision'] === 'rejected' && blank($validated['reason'] ?? null)) {
            return back()->withErrors(['reason' => 'Give a reason so she knows what to do next.']);
        }

        $guardian->forceFill([
            'status' => $validated['decision'] === 'verified'
                ? Guardian::STATUS_VERIFIED
                : Guardian::STATUS_REJECTED,
            'verified_at' => $validated['decision'] === 'verified' ? now() : null,
            'verified_by' => $request->user()->id,
            'rejection_reason' => $validated['reason'] ?? null,
        ])->save();

        return back()->with('success', 'Recorded.');
    }

    /**
     * ⚠️ THE JOINT PHOTOGRAPH, STREAMED THROUGH A CONTROLLER THAT ASKS WHO IS
     * LOOKING. It shows two identifiable people and is never public.
     */
    public function photo(Request $request, Guardian $guardian): StreamedResponse
    {
        $this->assertInScope($request, $guardian);

        abort_unless($guardian->joint_photo_path, 404);

        return Storage::disk(PhotoStorageService::DISK)->response($guardian->joint_photo_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    private function assertInScope(Request $request, Guardian $guardian): void
    {
        abort_unless(
            ReviewerScope::maySee($request->user(), $guardian->member?->profile?->gender),
            404
        );
    }
}
