<?php

namespace App\Services\Risk;

/**
 * A risk score, its reasons, and no decision.
 *
 * ⚠️ THERE IS NO `shouldReject()` ON THIS CLASS AND THERE MUST NEVER BE ONE.
 *
 * The brief requires that automated scoring cannot reject anybody. The way to
 * guarantee that is not a rule somebody follows — it is to make the rejection
 * inexpressible. This object carries a number and a list of sentences. It has
 * no method that returns a verdict, so there is no call a controller could make
 * to have the machine decide, and `RiskAssessmentTest` asserts that no such
 * method exists.
 *
 * `band()` returns advice for a human reading a queue. "Review" and "approve
 * with a reason" are instructions to a person, not actions.
 */
final class RiskAssessment
{
    public const BAND_LOW = 'low';

    public const BAND_REVIEW = 'review';

    public const BAND_HIGH = 'high';

    /**
     * @param  list<string>  $reasons  Plain English, for an admin, never codes.
     */
    public function __construct(
        public readonly int $score,
        public readonly array $reasons,
        public readonly ?string $summary = null,
        public readonly ?string $modelVersion = null,
        public readonly bool $modelUnavailable = false,
    ) {}

    /**
     * ⚠️ ADVICE FOR A PERSON, NOT A VERDICT.
     *
     * Under 30 suggests approving. 30 to 60 asks for a look. Over 60 still
     * permits approval — the admin simply has to type why, which is the
     * difference between a system that flags and a system that decides.
     */
    public function band(): string
    {
        return match (true) {
            $this->score > 60 => self::BAND_HIGH,
            $this->score >= 30 => self::BAND_REVIEW,
            default => self::BAND_LOW,
        };
    }

    /** Whether an admin approving this must give a reason. */
    public function requiresReasonToApprove(): bool
    {
        return $this->band() === self::BAND_HIGH;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'band' => $this->band(),
            'reasons' => $this->reasons,
            'summary' => $this->summary,
            'modelVersion' => $this->modelVersion,
            'modelUnavailable' => $this->modelUnavailable,
        ];
    }
}
