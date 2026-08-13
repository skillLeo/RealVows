<?php

namespace App\Http\Controllers;

use App\Mail\TrustedContactInvitation;
use App\Models\Guardian;
use App\Services\Guardian\GuardianService;
use App\Services\Guardian\GuardianGate;
use App\Support\GuardianPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The member's own trusted-contact screens.
 *
 * ⚠️ CALLED "TRUSTED CONTACT" EVERYWHERE A MEMBER CAN SEE IT. The internal
 * name is `guardian`; not one string on these screens says so.
 */
class TrustedContactController extends Controller
{
    public function __construct(
        private readonly GuardianService $service,
        private readonly GuardianGate $gate,
        private readonly GuardianPolicy $policy,
    ) {}

    public function show(Request $request): Response
    {
        $member = $request->user();

        /*
         * ⚠️ 404 WHEN THE FEATURE IS OFF, rather than a page explaining a
         * system the client has not switched on. The requirement is unset by
         * design, and a member who finds this URL today should find nothing.
         */
        abort_unless($this->policy->isOfferedTo($member->profile?->gender), 404);

        $current = Guardian::where('member_id', $member->id)
            ->whereNot('status', Guardian::STATUS_REPLACED)
            ->latest()
            ->first();

        return Inertia::render('TrustedContact/Show', [
            'required' => $this->policy->isRequiredFor($member->profile?->gender),

            // ⚠️ B9. Five declines with no reason is a pattern she cannot see.
            'offerSupport' => $this->gate->shouldOfferSupport($member),
            'state' => $this->gate->stateFor($member),
            'relationships' => collect(Guardian::RELATIONSHIPS)
                // `appointed` is set by staff on the no-contact path, never
                // chosen from a dropdown by a member.
                ->except('appointed')
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'current' => $current ? [
                'name' => $current->is_appointed ? null : $current->name,
                'status' => $current->status,
                'isAppointed' => $current->is_appointed,

                /*
                 * ⚠️ THE REASON, WHEN THERE IS ONE.
                 *
                 * A member whose trusted contact was turned down was told only
                 * that we "could not confirm" them. Without the reason there is
                 * nothing to act on — they cannot tell whether the photograph
                 * was unclear, the person declined, or something is wrong with
                 * the relationship they claimed. A rejection a member cannot
                 * understand is a rejection they will raise a support ticket
                 * about, and rightly.
                 */
                'rejectionReason' => $current->status === Guardian::STATUS_REJECTED
                    ? $current->rejection_reason
                    : null,
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $member = $request->user();

        abort_unless($this->policy->isOfferedTo($member->profile?->gender), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'relationship' => ['required', Rule::in(array_keys(collect(Guardian::RELATIONSHIPS)->except('appointed')->all()))],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
        ]);

        $result = $this->service->nominate($member, $validated);

        /*
         * ⚠️ A REJECTION IS SHOWN AS A VALIDATION ERROR ON THE FIELD, not as a
         * flash. The member needs to know WHICH detail we will not accept, and
         * a banner saying "something was wrong" invites them to retry the same
         * thing.
         *
         * ⚠️ THE MESSAGE NEVER SAYS WHOSE ACCOUNT THE DETAIL BELONGS TO. "That
         * mobile number is already registered to a member account" is enough
         * for an honest member to correct a typo, and tells somebody probing
         * nothing about who else is on the platform.
         */
        if ($result['rejections'] !== []) {
            return back()->withErrors(['email' => $result['rejections'][0]])->withInput();
        }

        $this->deliverInvitation($result['guardian'], $result['token']);

        return back()->with('success', 'We have sent them an invitation.');
    }

    /** B7. The member has nobody to name. */
    public function requestAppointed(Request $request): RedirectResponse
    {
        $member = $request->user();

        abort_unless($this->policy->isOfferedTo($member->profile?->gender), 404);

        $this->service->requestAppointed($member);

        return back()->with('success', 'We will arrange somebody and let you know.');
    }

    /**
     * ⚠️ EMAIL ONLY, AND THE REASON IS RECORDED RATHER THAN THE GAP HIDDEN.
     *
     * B3 asks for email and WhatsApp. A business-initiated WhatsApp message to
     * somebody who has never contacted us requires an approved template, and
     * none of the seven is an invitation. That needs an eighth submitted to
     * Meta. Until then this sends one channel and says so in the log.
     */
    private function deliverInvitation(Guardian $guardian, string $token): void
    {
        if ($guardian->email) {
            Mail::to($guardian->email)->send(new TrustedContactInvitation($guardian, $token));
        }

        $this->service->logMissingWhatsAppTemplate($guardian);
    }
}
