<?php

namespace App\Http\Controllers;

use App\Actions\Profile\SaveProfileStep;
use App\Actions\Verification\EnsureVerificationCode;
use App\Http\Requests\Profile\SaveStepRequest;
use App\Models\Guardian;
use App\Models\Profile;
use App\Services\Profile\ProfileCompleteness;
use App\Support\ProfileOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class ProfileBuilderController extends Controller
{
    /** URL segment => position in the seven. */
    public const STEP_KEYS = [
        'account' => 1,
        'personal' => 2,
        'physical' => 3,
        'photos' => 4,
        'lifestyle' => 5,
        'values' => 6,
        'preferences' => 7,
    ];

    public function __construct(private readonly ProfileCompleteness $completeness) {}

    public function show(Request $request, ?string $step = null): Response|RedirectResponse
    {
        $profile = $this->profileFor($request);

        // No step given: drop them back where they stopped.
        if ($step === null) {
            $resume = array_search($profile->current_step, self::STEP_KEYS, true) ?: 'account';

            return redirect()->route('profile.step', $resume);
        }

        $step = strtolower($step);
        abort_unless(array_key_exists($step, self::STEP_KEYS), 404);

        // The code has to exist while they are still holding their phone on
        // the photos step, not be generated afterwards.
        $verification = $step === 'photos'
            ? app(EnsureVerificationCode::class)->handle($profile)
            : $profile->verification;

        $assessment = $this->completeness->assess($profile);

        return Inertia::render('Profile/Builder', [
            'photos' => $profile->photos->mapWithKeys(fn ($photo) => [
                $photo->slot => [
                    'id' => $photo->id,
                    'url' => URL::temporarySignedRoute('photos.show', now()->addHour(), ['photo' => $photo->id]),
                ],
            ]),
            'verification' => [
                'code' => $verification?->code,
                'status' => $verification?->status,
                'selfie_url' => $verification?->selfie_path
                    ? URL::temporarySignedRoute('verification.selfie.show', now()->addHour(), ['verification' => $verification->id])
                    : null,
            ],
            'steps' => ProfileOptions::STEPS,
            'stepKeys' => array_keys(self::STEP_KEYS),
            'currentStep' => $step,
            'currentStepNumber' => self::STEP_KEYS[$step],
            'options' => ProfileOptions::allForBrowser(),
            'profile' => $this->profilePayload($profile, $request),
            'completeness' => $assessment['percent'],
            'missing' => $assessment['missing'],
            'submittable' => $assessment['missing'] === [],
            'missingByStep' => $assessment['missingByStep'],
            'submittedAt' => $profile->submitted_at?->toIso8601String(),
        ]);
    }

    /**
     * Autosave target. Hit every 800ms while somebody is typing, so it stays
     * small: validate, persist, hand back the new completeness figure and
     * nothing else.
     */
    public function save(SaveStepRequest $request, SaveProfileStep $action, string $step): JsonResponse
    {
        $step = strtolower($step);
        abort_unless(array_key_exists($step, self::STEP_KEYS), 404);

        $profile = $this->profileFor($request);

        $assessment = $action->handle(
            $profile,
            $step,
            $request->validated(),
            self::STEP_KEYS[$step],
        );

        return response()->json([
            'saved_at' => now()->toIso8601String(),
            'completeness' => $assessment['percent'],
            'missing' => $assessment['missing'],
            'submittable' => $assessment['missing'] === [],
            'missingByStep' => $assessment['missingByStep'],
        ]);
    }

    /**
     * Submit for verification.
     *
     * A member cannot submit an incomplete profile — but being refused
     * without being told why is the kind of dead end that makes people give
     * up, so the refusal names exactly what is still missing, in plain words.
     */
    public function submit(Request $request, \App\Services\Settings\RuntimeMailConfigurator $mail): RedirectResponse
    {
        $profile = $this->profileFor($request);
        $assessment = $this->completeness->assess($profile);

        if ($assessment['missing'] !== []) {
            return back()->with('error', 'Almost there. Still needed: '.
                collect($assessment['missing'])->map(fn ($item) => lcfirst($item))->implode(', ').'.');
        }

        if ($profile->submitted_at) {
            return redirect()->route('dashboard');
        }

        $profile->forceFill(['submitted_at' => now()])->save();

        /*
         * ⚠️ THE VERIFICATION EMAIL SENDS HERE, NOT AT REGISTRATION.
         *
         * The client: "the whole thing is after the profile is done, and you
         * say, thank you for creating your profile, welcome to RealVows, please
         * click this link to confirm your email address."
         *
         * By this point they have written about themselves and uploaded
         * photographs. Clicking a link is a small thing at the end rather than
         * a toll on the way in.
         */
        if (! $request->user()->hasVerifiedEmail()) {
            $mail->apply();
            $request->user()->sendEmailVerificationNotification();
        }

        /*
         * ⚠️ THE REVIEW QUEUE STAYS SHUT UNTIL THE ADDRESS IS CONFIRMED.
         *
         * A reviewer's time is the scarcest thing on this platform, and every
         * row here is a real person opening real photographs. Somebody who
         * typed an address they cannot receive mail at is not a member yet, and
         * queueing them spends that time on a profile that may never become
         * anybody.
         *
         * ⚠️ THE PROFILE IS STILL SUBMITTED. Their work is saved and complete —
         * this holds the QUEUE, not the member. Confirming the address moves
         * them into it with nothing to redo. See EmailVerificationController.
         */
        if ($request->user()->hasVerifiedEmail()) {
            $profile->verification()->getQuery()->where('status', '!=', 'verified')->update([
                'status' => 'pending',
                'updated_at' => now(),
            ]);
        }

        /*
         * ⚠️ STRAIGHT TO THE TRUSTED CONTACT, NOT TO THE DASHBOARD.
         *
         * Naming a trusted contact is not part of building a profile and it is
         * the thing the whole platform turns on. Left as its own page reached
         * from a menu, it is the step nobody takes: a member finishes seven
         * steps, lands on a dashboard, feels finished, and never goes looking
         * for it.
         *
         * ⚠️ THEY CAN SKIP IT, AND THAT MATTERS. This is a redirect, not a
         * gate — the page explains who a trusted contact is and why, in plain
         * words, and offers a way past. Forcing it would mean a member who has
         * nobody to name simply cannot finish, which is precisely the person
         * the appointed-contact path exists for.
         */
        $alreadyNamed = Guardian::where('member_id', $request->user()->id)
            ->whereNot('status', Guardian::STATUS_REPLACED)
            ->exists();

        if (! $alreadyNamed) {
            return redirect()->route('trusted-contact.show')
                ->with('success', 'Your profile is with our team. A real person will check it, '
                    .'usually within a day. There is one more thing worth doing while you wait.');
        }

        return redirect()->route('dashboard')
            ->with('success', 'Your profile is with our team. A real person will check it, usually within a day.');
    }

    private function profileFor(Request $request): Profile
    {
        /*
         * A member only ever reaches their own profile — resolved from the
         * session, never from an id in the URL, so there is no id to change
         * and nothing for a policy to catch.
         *
         * firstOrCreate rather than firstOrFail: registration always makes a
         * profile, but an administrator created from the console has none,
         * and a 404 on their own Profile tab is a confusing dead end. Creating
         * the empty row costs nothing and lets staff walk the member flow.
         */
        $user = $request->user();

        $user->profile()->firstOrCreate(['user_id' => $user->id]);

        return $user->profile()->with(['preferences', 'photos'])->firstOrFail();
    }

    /**
     * Explicit allow-list.
     *
     * ⚠️ `sign` and `element` are absent by construction, not by filtering.
     * The model hides them too, but naming every field that leaves the
     * server means a future column is opt-in rather than opt-out.
     */
    private function profilePayload(Profile $profile, Request $request): array
    {
        $preferences = $profile->preferences;

        return [
            'account' => [
                'email' => $request->user()->email,
                'phone_country_code' => $request->user()->phone_country_code,
                'phone_number' => $request->user()->phone_number,
                'phone_verified' => $request->user()->phoneIsVerified(),
                'email_verified' => $request->user()->hasVerifiedEmail(),
            ],
            'personal' => [
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'date_of_birth' => $profile->date_of_birth?->toDateString(),
                'gender' => $profile->gender,
                'seeking' => $profile->seeking,
                'city' => $profile->city,
                'region' => $profile->region,
                'country' => $profile->country,
                'willing_to_relocate' => $profile->willing_to_relocate,
                'nationality' => $profile->nationality,
            ],
            'physical' => [
                'height_cm' => $profile->height_cm,
                'body_type' => $profile->body_type,
                'eye_colour' => $profile->eye_colour,
                'hair_colour' => $profile->hair_colour,
                'has_tattoos' => $profile->has_tattoos,
                'has_piercings' => $profile->has_piercings,
            ],
            'lifestyle' => [
                'education' => $profile->education,
                'occupation' => $profile->occupation,
                'industry' => $profile->industry,
                'income_range' => $profile->income_range,
                'religion' => $profile->religion,
                'faith_importance' => $profile->faith_importance,
                'smoking' => $profile->smoking,
                'drinking' => $profile->drinking,
                'has_children' => $profile->has_children,
                'wants_children' => $profile->wants_children,
                'pets' => $profile->pets ?? [],
                'languages' => $profile->languages ?? [],
            ],
            'values' => [
                'relationship_goal' => $profile->relationship_goal,
                'marriage_timeline' => $profile->marriage_timeline,
                'core_values' => $profile->core_values ?? [],
                'conflict_style' => $profile->conflict_style,
                'love_languages' => $profile->love_languages ?? [],
                'bio' => $profile->bio,
                'interests' => $profile->interests ?? [],
                'personality_scales' => $profile->personality_scales ?? [],
            ],
            'preferences' => [
                'age_min' => $preferences?->age_min,
                'age_max' => $preferences?->age_max,
                'height_min_cm' => $preferences?->height_min_cm,
                'height_max_cm' => $preferences?->height_max_cm,
                'body_types' => $preferences?->body_types ?? [],
                'distance_preference' => $preferences?->distance_preference,
                'deal_breakers' => $preferences?->deal_breakers ?? [],
                'preferred_qualities' => $preferences?->preferred_qualities ?? [],
            ],
        ];
    }
}
