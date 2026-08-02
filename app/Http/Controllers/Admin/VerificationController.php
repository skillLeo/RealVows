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

        return Storage::disk(PhotoStorageService::DISK)->response($photo->path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    public function selfie(Verification $verification): StreamedResponse
    {
        abort_unless($verification->selfie_path !== null, 404);

        return Storage::disk(PhotoStorageService::DISK)->response($verification->selfie_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
        ]);
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
