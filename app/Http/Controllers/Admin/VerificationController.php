<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewVerificationRequest;
use App\Models\Photo;
use App\Models\Verification;
use App\Services\Photos\PhotoStorageService;
use App\Services\Verification\VerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    public function __construct(private readonly VerificationService $verifications) {}

    /**
     * The queue: pending first, oldest first.
     *
     * Oldest first is deliberate. Someone who submitted three days ago has
     * been waiting three days, and a newest-first queue would leave them
     * waiting indefinitely while fresher submissions kept arriving.
     */
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'pending';

        $verifications = Verification::query()
            ->when(
                $status === 'pending',
                fn ($query) => $query->whereIn('status', [Verification::STATUS_PENDING, Verification::STATUS_REVIEW]),
                fn ($query) => $query->where('status', $status)
            )
            ->whereHas('profile', fn ($query) => $query->whereNotNull('submitted_at'))
            // ⚠️ Gender separation, applied to the QUERY. See ReviewerScope.
            ->tap(fn ($query) => \App\Support\ReviewerScope::applyToProfileRelation($query, $request->user()))
            ->with([
                'profile:id,user_id,first_name,last_name,date_of_birth,city,country',
                'profile.photos:id,profile_id,slot',
            ])
            ->oldest('updated_at')
            ->paginate(20)
            ->through(fn (Verification $verification) => [
                'id' => $verification->id,
                'status' => $verification->status,
                'attempt' => $verification->attempt_number,
                'waitingSince' => $verification->updated_at?->diffForHumans(),
                'name' => trim(($verification->profile?->first_name ?? '').' '.($verification->profile?->last_name ?? '')) ?: 'Unnamed',
                'age' => $verification->profile?->age(),
                'city' => collect([$verification->profile?->city, $verification->profile?->country])->filter()->implode(', '),
                'photoCount' => $verification->profile?->photos->count() ?? 0,
                'thumbnail' => $this->thumbnailUrl($verification),
            ]);

        return Inertia::render('Admin/Verifications/Index', [
            // The reports queue is reached from here, so its count belongs here.
            'openReports' => \App\Models\Report::where('status', \App\Models\Report::STATUS_OPEN)->count(),

            /*
             * ⚠️ BOTH TRUSTED-CONTACT QUEUES IN ONE NUMBER: contacts awaiting
             * review, AND members waiting for us to appoint somebody. Counting
             * only the first would leave the forgotten queue invisible in the
             * one place staff actually look.
             */
            'openTrustedContacts' => \App\Models\Guardian::query()
                ->where(fn ($q) => $q
                    ->where('status', \App\Models\Guardian::STATUS_SUBMITTED)
                    ->orWhere(fn ($a) => $a->where('is_appointed', true)->whereNull('user_id'))
                )
                ->whereNot('status', \App\Models\Guardian::STATUS_REPLACED)
                ->count(),
            'verifications' => $verifications,
            'status' => $status,
            // A count per filter, so each tab says how much work is behind it
            // rather than making the reviewer click to find out.
            'counts' => $this->counts(),
        ]);
    }

    public function show(Verification $verification): Response
    {
        $verification->load(['profile.photos', 'profile.user:id,email', 'reviewer:id,name']);

        /*
         * ⚠️ 404, NEVER 403. A 403 tells a reviewer probing ids that the record
         * exists and is somebody else's. A 404 tells them nothing.
         */
        abort_unless(
            \App\Support\ReviewerScope::maySee(request()->user(), $verification->profile?->gender),
            404
        );

        return Inertia::render('Admin/Verifications/Show', [
            'verification' => [
                'id' => $verification->id,
                'status' => $verification->status,
                'code' => $verification->code,
                'attempt' => $verification->attempt_number,
                'reviewedBy' => $verification->reviewer?->name,
                'reviewedAt' => $verification->reviewed_at?->format('j F Y, H:i'),
                'rejectionReason' => $verification->rejection_reason,
                'selfieUrl' => $verification->selfie_path
                    ? URL::signedRoute('admin.verifications.selfie', ['verification' => $verification->id])
                    : null,
            ],
            'member' => [
                'name' => trim(($verification->profile->first_name ?? '').' '.($verification->profile->last_name ?? '')),
                'age' => $verification->profile->age(),
                'city' => collect([$verification->profile->city, $verification->profile->country])->filter()->implode(', '),
                'email' => $verification->profile->user?->email,
            ],
            'photos' => $verification->profile->photos->map(fn (Photo $photo) => [
                'id' => $photo->id,
                'slot' => $photo->slot,
                'title' => $photo->title(),
                'url' => URL::signedRoute('admin.verifications.photo', [
                    'verification' => $verification->id,
                    'photo' => $photo->id,
                ]),
            ])->values(),
            'reasons' => $this->verifications->rejectionReasons(),
        ]);
    }

    public function review(ReviewVerificationRequest $request, Verification $verification): RedirectResponse
    {
        if ($request->validated('decision') === 'approve') {
            $this->verifications->approve($verification, $request->user());

            return redirect()
                ->route('admin.verifications')
                ->with('success', 'Approved. The member has been told.');
        }

        $this->verifications->reject(
            $verification,
            $request->user(),
            $request->validated('reason'),
            $request->validated('note'),
        );

        return redirect()
            ->route('admin.verifications')
            ->with('success', 'Sent back with guidance. The member can try again.');
    }

    /**
     * Admin-side photo reads.
     *
     * Separate from the member routes on purpose: a reviewer needs to see
     * somebody else's photographs, which is precisely what the member routes
     * forbid. Keeping it a distinct, permission-gated, signed route means the
     * member-side ownership rule stays absolute rather than growing an
     * exception that could be widened by accident later.
     */
    public function photo(Verification $verification, Photo $photo): StreamedResponse
    {
        abort_unless($photo->profile_id === $verification->profile_id, 404);

        /*
         * ⚠️ THE CHECK BELONGS HERE, not only on the page that links to it.
         * This route streams the bytes. A reviewer who is shown no thumbnail
         * can still type this URL, and until this line existed it would have
         * been served.
         */
        $this->assertInScope($verification);

        return Storage::disk(PhotoStorageService::DISK)->response($photo->path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    public function selfie(Verification $verification): StreamedResponse
    {
        abort_unless($verification->selfie_path !== null, 404);

        $this->assertInScope($verification);

        return Storage::disk(PhotoStorageService::DISK)->response($verification->selfie_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    /**
     * ⚠️ ONE PLACE THAT DECIDES, AND IT LOGS THE UNRESTRICTED CASE.
     *
     * Super admin keeps full access, because somebody has to be able to
     * resolve a dispute about a review. That access is recorded: an
     * unrestricted account reading a member's photographs is exactly the thing
     * that should leave a trail, and "the owner can see everything" is only
     * acceptable if the owner can be asked about it afterwards.
     */
    private function assertInScope(Verification $verification): void
    {
        $user = request()->user();
        $gender = $verification->profile?->gender;

        abort_unless(\App\Support\ReviewerScope::maySee($user, $gender), 404);

        if (\App\Support\ReviewerScope::gendersFor($user) === null) {
            \Illuminate\Support\Facades\Log::info('super admin viewed a verification photograph', [
                'user_id' => $user?->id,
                'verification_id' => $verification->id,
                'member_gender' => $gender,
            ]);
        }
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $submitted = fn ($query) => $query->whereHas('profile', fn ($q) => $q->whereNotNull('submitted_at'));

        return [
            'pending' => Verification::query()
                ->whereIn('status', [Verification::STATUS_PENDING, Verification::STATUS_REVIEW])
                ->tap($submitted)->count(),
            'verified' => Verification::query()
                ->where('status', Verification::STATUS_VERIFIED)
                ->tap($submitted)->count(),
            'rejected' => Verification::query()
                ->where('status', Verification::STATUS_REJECTED)
                ->tap($submitted)->count(),
        ];
    }

    private function thumbnailUrl(Verification $verification): ?string
    {
        $photo = $verification->profile?->photos->firstWhere('slot', 'passport')
            ?? $verification->profile?->photos->first();

        return $photo
            ? URL::signedRoute('admin.verifications.photo', [
                'verification' => $verification->id,
                'photo' => $photo->id,
            ])
            : null;
    }
}
