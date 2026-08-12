<?php

namespace App\Services\Guardian;

use App\Models\Guardian;
use App\Models\User;
use Carbon\Carbon;

/**
 * Whether a nominated trusted contact is plausible, checked BEFORE anything is
 * sent.
 *
 * ⚠️ THE ORDER MATTERS. These run at submission, not after the invitation goes
 * out. An invitation is an email and a WhatsApp message to a real phone number;
 * sending one and then discovering the number belongs to the member herself
 * means we have already messaged a stranger, or worse, helped somebody
 * construct a fake guardian and only noticed afterwards.
 *
 * ── ⚠️ REJECT VERSUS FLAG ─────────────────────────────────────────────────
 *
 * A rejection stops the member. A flag lets them continue and tells the
 * reviewer where to look. The difference is whether an innocent explanation
 * exists.
 *
 * There is no innocent reason for a guardian's phone to be a member's login
 * phone: that is one person operating both accounts, which is the single
 * highest-signal fraud case in the system. There IS an innocent reason for one
 * phone to appear on four guardian records — a father with four daughters —
 * so that is flagged and never rejected.
 */
class GuardianIntegrity
{
    /** More than this many wards on one number is worth a human look. */
    public const WARD_LIMIT = 3;

    /** Minimum plausible age gaps, in years, by declared relationship. */
    public const MINIMUM_GAP = [
        'father' => 16,
        'paternal_grandfather' => 32,
        'paternal_uncle' => 10,
    ];

    /**
     * @return array{rejections: list<string>, flags: list<string>}
     */
    public function check(User $member, array $candidate): array
    {
        $rejections = [];
        $flags = [];

        $phone = $this->normalisePhone($candidate['phone'] ?? null);
        $email = strtolower(trim((string) ($candidate['email'] ?? '')));

        /*
         * ⚠️ HARD REJECT. The guardian's contact details belong to a member
         * account. One person is holding both sides of a check designed to
         * involve two.
         */
        if ($phone && $this->phoneBelongsToAnyMember($phone, $member)) {
            $rejections[] = 'That mobile number is already registered to a member account.';
        }

        if ($email !== '' && User::where('email', $email)->whereKeyNot($member->id)->exists()) {
            $rejections[] = 'That email address is already registered to an account.';
        }

        /*
         * ⚠️ PLUS-ALIASES AND DOT-VARIANTS. `aisha+dad@gmail.com` and
         * `a.i.s.h.a@gmail.com` both deliver to the member's own inbox, which
         * means she would be approving her own introductions while the system
         * believed a second person was involved.
         */
        if ($email !== '' && $this->isAliasOf($email, (string) $member->email)) {
            $rejections[] = 'That email address delivers to your own inbox.';
        }

        // ── Flags. A human decides. ──────────────────────────────────────
        if ($phone) {
            $wards = Guardian::where('phone', $phone)
                ->whereNot('status', Guardian::STATUS_REPLACED)
                ->distinct('member_id')
                ->count('member_id');

            if ($wards >= self::WARD_LIMIT) {
                $flags[] = "This number is already the trusted contact for {$wards} other members.";
            }
        }

        foreach ($this->ageGapFlags($member, $candidate) as $flag) {
            $flags[] = $flag;
        }

        return ['rejections' => $rejections, 'flags' => $flags];
    }

    /**
     * ⚠️ THE DECLARED AGE IS SELF-REPORTED AND IS NOT EVIDENCE.
     *
     * Nothing here proves anything. A member constructing a fake father can
     * type any birth year she likes. What an impossible gap does is tell the
     * reviewer to look harder at the joint photograph, which is the actual
     * check. So these are flags, never rejections, and the message says what
     * the reviewer should compare rather than asserting a conclusion.
     *
     * @return list<string>
     */
    private function ageGapFlags(User $member, array $candidate): array
    {
        $memberDob = $member->profile?->date_of_birth;
        $guardianDob = ($candidate['date_of_birth'] ?? null)
            ? Carbon::parse($candidate['date_of_birth'])
            : null;

        if (! $memberDob || ! $guardianDob) {
            return [];
        }

        $gap = $guardianDob->diffInYears($memberDob, absolute: false);
        $relationship = (string) ($candidate['relationship'] ?? '');

        if (isset(self::MINIMUM_GAP[$relationship]) && $gap < self::MINIMUM_GAP[$relationship]) {
            $needed = self::MINIMUM_GAP[$relationship];

            return ["A {$relationship} would normally be at least {$needed} years older. "
                .'Compare the joint photograph.'];
        }

        if ($relationship === 'brother' && abs($gap) > 25) {
            return ['A 25-year gap between siblings is unusual. Compare the joint photograph.'];
        }

        return [];
    }

    private function phoneBelongsToAnyMember(string $phone, User $member): bool
    {
        return User::query()
            ->whereKeyNot($member->id)
            ->whereRaw("REPLACE(REPLACE(REPLACE(CONCAT(COALESCE(phone_country_code,''), COALESCE(phone_number,'')), ' ', ''), '-', ''), '+', '') = ?", [$phone])
            ->exists();
    }

    /**
     * ⚠️ GMAIL IGNORES DOTS AND EVERYTHING AFTER A PLUS. Most providers ignore
     * the plus. The comparison strips both from BOTH addresses, so
     * `a.i.sha+dad@gmail.com` and `aisha@gmail.com` compare equal.
     */
    private function isAliasOf(string $candidate, string $member): bool
    {
        return $this->canonicalEmail($candidate) === $this->canonicalEmail($member);
    }

    private function canonicalEmail(string $email): string
    {
        $email = strtolower(trim($email));
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $local = explode('+', $local)[0];
        $local = str_replace('.', '', $local);

        return $local.'@'.$domain;
    }

    private function normalisePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        return $digits === '' ? null : $digits;
    }
}
