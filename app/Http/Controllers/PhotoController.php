<?php

namespace App\Http\Controllers;

use App\Actions\Verification\EnsureVerificationCode;
use App\Http\Requests\Profile\UploadPhotoRequest;
use App\Models\MatchRecord;
use App\Models\Photo;
use App\Models\Verification;
use App\Services\Photos\PhotoStorageService;
use App\Services\Profile\ProfileCompleteness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoController extends Controller
{
    public function __construct(
        private readonly PhotoStorageService $storage,
        private readonly ProfileCompleteness $completeness,
    ) {}

    public function store(UploadPhotoRequest $request): JsonResponse
    {
        $profile = $request->user()->profile;
        $slot = $request->validated('slot');

        $existing = $profile->photos()->where('slot', $slot)->first();

        $stored = $this->storage->store($request->file('photo'), $profile->id, $slot);

        $photo = $profile->photos()->updateOrCreate(['slot' => $slot], $stored);

        // Only after the replacement is safely written. Deleting first would
        // lose the old photo if the new one failed to process.
        if ($existing) {
            $this->storage->delete($existing->path, $existing->webp_path, $existing->thumbnail_path);
        }

        return response()->json([
            'photo' => $this->payload($photo),
            ...$this->completenessPayload($profile),
        ]);
    }

    public function destroy(Request $request, Photo $photo): JsonResponse
    {
        $this->authorizeOwnership($request, $photo);

        $this->storage->delete($photo->path, $photo->webp_path, $photo->thumbnail_path);
        $photo->delete();

        return response()->json($this->completenessPayload($request->user()->profile->fresh(['photos'])));
    }

    /**
     * Serve a private photo.
     *
     * The route is signed AND authenticated AND ownership-checked. A signed
     * URL alone would still work if it were forwarded to somebody else; the
     * ownership check is what makes that useless.
     */
    public function show(Request $request, Photo $photo): StreamedResponse
    {
        $this->authorizeOwnership($request, $photo);

        $wantsWebp = str_contains((string) $request->header('Accept'), 'image/webp');
        $path = $wantsWebp && $photo->webp_path ? $photo->webp_path : $photo->path;

        return Storage::disk(PhotoStorageService::DISK)->response($path, null, [
            'Content-Type' => $wantsWebp && $photo->webp_path ? 'image/webp' : 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * The verification selfie, shown back to the member who uploaded it so
     * they can check it is legible. It is never rendered on a profile and
     * never served to another member — the ownership check below is the only
     * door, and an admin reviewing the queue reads it by a separate route.
     */
    public function showVerificationSelfie(Request $request, Verification $verification): StreamedResponse
    {
        abort_unless($verification->profile_id === $request->user()->profile?->id, 404);
        abort_unless($verification->selfie_path !== null, 404);

        return Storage::disk(PhotoStorageService::DISK)->response($verification->selfie_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    public function storeVerificationSelfie(Request $request, EnsureVerificationCode $codes): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.(PhotoStorageService::MAX_BYTES / 1024)],
        ], [
            'photo.image' => 'That file does not look like a photo. JPEG, PNG or WebP, please.',
        ]);

        $profile = $request->user()->profile;
        $verification = $codes->handle($profile);

        $previous = [$verification->selfie_path, $verification->selfie_webp_path];

        $verification->update(
            $this->storage->storeVerificationSelfie($request->file('photo'), $profile->id)
        );

        $this->storage->delete(...$previous);

        return back()->with('success', 'Verification photo received.');
    }

    public function regenerateCode(Request $request, EnsureVerificationCode $codes): RedirectResponse
    {
        $codes->regenerate($codes->handle($request->user()->profile));

        return back()->with('success', 'Here is a new code. Please write this one on your note.');
    }

    /**
     * A member may see a photograph in exactly two cases: it is theirs, or
     * they have PAID to open an introduction to the person in it.
     *
     * ⚠️ This second case was missing, and it was a live bug. Phase 1's rule
     * was "your own photos only", which was correct while the profile builder
     * was the only thing showing photographs. Phase 2 added the match view
     * and MatchPresenter began emitting signed URLs for the OTHER person's
     * photos — which this method then refused. Members were paying to open an
     * introduction and getting four broken images.
     *
     * It was found by a test that followed the URL the serialiser actually
     * emitted, rather than rebuilding one. Nothing else would have caught it:
     * the URL was correctly signed, the route existed, the photo existed, and
     * the authorisation said no.
     *
     * ⚠️ A TEASER GRANTS NOTHING. Only `status = unlocked` opens this door,
     * which is the same rule the presenter applies when deciding whether to
     * generate a URL at all. Two independent checks, same answer.
     */
    private function authorizeOwnership(Request $request, Photo $photo): void
    {
        $profileId = $request->user()->profile?->id;

        // 404 rather than 403: another member's photo id should look like it
        // does not exist, because as far as this member is concerned it does not.
        abort_if($profileId === null, 404);

        if ($photo->profile_id === $profileId) {
            return;
        }

        $hasPaidToSeeThem = MatchRecord::query()
            ->where('seeker_profile_id', $profileId)
            ->where('matched_profile_id', $photo->profile_id)
            ->where('status', MatchRecord::STATUS_UNLOCKED)
            ->exists();

        abort_unless($hasPaidToSeeThem, 404);
    }

    private function payload(Photo $photo): array
    {
        return [
            'id' => $photo->id,
            'slot' => $photo->slot,
            // A short-lived signed URL. Nothing in the markup is a permanent
            // or guessable address for a real person's photograph.
            'url' => URL::temporarySignedRoute('photos.show', now()->addHour(), ['photo' => $photo->id]),
        ];
    }

    private function completenessPayload($profile): array
    {
        $assessment = $this->completeness->assess($profile->fresh(['photos', 'preferences']));

        $profile->forceFill(['completeness_percent' => $assessment['percent']])->saveQuietly();

        return [
            'completeness' => $assessment['percent'],
            'missing' => $assessment['missing'],
            'submittable' => $assessment['missing'] === [],
        ];
    }
}
