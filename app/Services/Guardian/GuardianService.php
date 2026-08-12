<?php

namespace App\Services\Guardian;

use App\Models\Guardian;
use App\Models\GuardianInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Naming a trusted contact, and inviting them.
 *
 * ⚠️ INTEGRITY IS CHECKED BEFORE ANYTHING IS SENT, NOT AFTER.
 *
 * An invitation is an email and a WhatsApp message to a real person. Creating
 * the record first and validating afterwards means we have already messaged a
 * stranger by the time we discover the number belongs to the member herself —
 * or worse, helped somebody construct a fake guardian and only noticed later.
 * `GuardianIntegrity` runs first and a rejection stops everything.
 */
class GuardianService
{
    public function __construct(
        private readonly GuardianIntegrity $integrity,
    ) {}

    /**
     * @param  array{name: string, relationship: string, phone?: ?string, email?: ?string, date_of_birth?: ?string}  $details
     * @return array{guardian: ?Guardian, token: ?string, rejections: list<string>, flags: list<string>}
     */
    public function nominate(User $member, array $details): array
    {
        $check = $this->integrity->check($member, $details);

        if ($check['rejections'] !== []) {
            return [
                'guardian' => null,
                'token' => null,
                'rejections' => $check['rejections'],
                'flags' => $check['flags'],
            ];
        }

        return DB::transaction(function () use ($member, $details, $check) {
            /*
             * ⚠️ AN EARLIER NOMINATION IS MARKED `replaced`, NEVER DELETED.
             *
             * The decision history is evidence. B9 judges whether a trusted
             * contact is behaving unreasonably by reading the sequence of their
             * declines, and deleting the row deletes the reason somebody was
             * replaced in the first place.
             */
            Guardian::where('member_id', $member->id)
                ->whereNot('status', Guardian::STATUS_REPLACED)
                ->update(['status' => Guardian::STATUS_REPLACED]);

            $guardian = Guardian::create([
                'member_id' => $member->id,
                'name' => $details['name'],
                'relationship' => $details['relationship'],
                'phone' => $details['phone'] ?? null,
                'email' => $details['email'] ?? null,
                'is_appointed' => false,
                'status' => Guardian::STATUS_INVITED,
                'risk_reasons' => $check['flags'] ?: null,
            ]);

            ['token' => $token] = GuardianInvitation::issue($guardian);

            return [
                'guardian' => $guardian,
                'token' => $token,
                'rejections' => [],
                'flags' => $check['flags'],
            ];
        });
    }

    /**
     * The member has nobody to name (B7).
     *
     * ⚠️ NO NAME FIELD, NO PHONE FIELD, NO LIST TO CHOOSE FROM.
     *
     * There is deliberately no public list of imams or organisations. Nothing
     * for a member to type means nothing to abuse, and a list would be a
     * directory of real people published by a platform whose entire premise is
     * that it publishes no directory.
     *
     * The row is created `is_appointed = true`, which is what triggers the
     * photograph rule in GuardianPresenter. Staff fill in who it is later.
     */
    public function requestAppointed(User $member): Guardian
    {
        Guardian::where('member_id', $member->id)
            ->whereNot('status', Guardian::STATUS_REPLACED)
            ->update(['status' => Guardian::STATUS_REPLACED]);

        return Guardian::create([
            'member_id' => $member->id,
            'name' => 'Awaiting appointment',
            'relationship' => 'appointed',
            'is_appointed' => true,
            'status' => Guardian::STATUS_INVITED,
        ]);
    }

    /**
     * A trusted contact accepts and creates their own account.
     *
     * ⚠️ THEIR OWN ACCOUNT, THEIR OWN PASSWORD. Not a magic link that logs
     * them in forever, and not an account the member sets up on their behalf —
     * a guardian a member can log into is not a second person, and the whole
     * system rests on there being two.
     */
    public function accept(string $token, string $name, string $email, string $password): ?Guardian
    {
        $invitation = GuardianInvitation::findByToken($token);

        if (! $invitation) {
            return null;
        }

        return DB::transaction(function () use ($invitation, $name, $email, $password) {
            $guardian = $invitation->guardian;

            if ($guardian->status === Guardian::STATUS_REPLACED) {
                return null;
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
            ]);

            $user->assignRole('guardian');

            $guardian->forceFill([
                'user_id' => $user->id,
                'status' => Guardian::STATUS_REGISTERED,
            ])->save();

            $invitation->consume();

            return $guardian->fresh();
        });
    }

    /**
     * ⚠️ WHATSAPP DELIVERY OF THE INVITATION IS NOT BUILT, AND CANNOT BE
     * WITHOUT A NEW META TEMPLATE.
     *
     * B3 asks for the invitation to go by email and WhatsApp. There are seven
     * approved templates and none of them is an invitation — a business-
     * initiated WhatsApp message to somebody who has never contacted us can
     * only be sent from an approved template, so this needs an eighth
     * submitted and reviewed.
     *
     * Recorded here rather than quietly sending email only and calling B3 done.
     * Email works today; the WhatsApp half is blocked on Meta.
     */
    public function invitationChannelsAvailable(): array
    {
        return ['email'];
    }

    public function logMissingWhatsAppTemplate(Guardian $guardian): void
    {
        Log::info('trusted contact invited by email only', [
            'guardian_id' => $guardian->id,
            'reason' => 'No approved WhatsApp template exists for an invitation. A business-'
                .'initiated message requires one, so this needs an eighth template submitted '
                .'to Meta before the WhatsApp half of B3 can work.',
        ]);
    }
}
