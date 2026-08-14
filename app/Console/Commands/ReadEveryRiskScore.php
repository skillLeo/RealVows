<?php

namespace App\Console\Commands;

use App\Models\Guardian;
use App\Services\Risk\GuardianRiskScorer;
use Illuminate\Console\Command;

/**
 * ⚠️ PRINTS EVERY SCORE THIS SYSTEM WOULD PRODUCE, SO A PERSON CAN READ THEM.
 *
 * ── Why this is not a test ────────────────────────────────────────────────
 *
 * The scorer is tested and the arithmetic is right. That is not the question.
 *
 * This is untested JUDGEMENT rather than untested code. It produces a number
 * and a set of reasons that a human being then acts on, about a real person, and
 * until somebody sits and reads a full run of them nobody knows whether the
 * bands are set sensibly or whether the reasons are convincing. A test can
 * assert that "same device" is worth forty points. It cannot tell you that
 * forty is the right number, or that a reviewer reading "activity shadows the
 * member" would understand what to do about it.
 *
 * ⚠️ THE FIRST TIME ANYBODY READS THIS OUTPUT MUST NOT BE IN PRODUCTION, about
 * a real member, at the moment it matters. That is the whole reason this
 * command exists rather than a note in a document saying somebody should check.
 *
 * ── What to look for when reading it ──────────────────────────────────────
 *
 *   · Does every HIGH band deserve to be high?
 *   · Is anything sitting at LOW that should have been caught?
 *   · Would the reasons make sense to a reviewer who did not write them?
 *   · Does any single signal dominate everything else?
 */
class ReadEveryRiskScore extends Command
{
    protected $signature = 'realvows:read-risk-scores {--band= : Only show one band}';

    protected $description = 'Print every trusted-contact risk score for a human to read.';

    public function __construct(private readonly GuardianRiskScorer $scorer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $guardians = Guardian::with('member.profile')->orderBy('id')->get();

        if ($guardians->isEmpty()) {
            /*
             * ⚠️ NOTHING TO READ IS NOT A PASS. An empty run prints a clean
             * table and tells a reader that everything is fine, which is the
             * failure this project has met repeatedly.
             */
            $this->error('There are no trusted contacts to score, so this run shows nothing.');

            return self::FAILURE;
        }

        $rows = [];
        $bands = [];

        foreach ($guardians as $guardian) {
            $assessment = $this->scorer->score($guardian);
            $band = $assessment->band();

            $bands[$band] = ($bands[$band] ?? 0) + 1;

            if ($this->option('band') && $this->option('band') !== $band) {
                continue;
            }

            $rows[] = [
                $guardian->id,
                mb_substr((string) $guardian->name, 0, 20),
                $guardian->relationship,
                $guardian->is_appointed ? 'appointed' : 'family',
                $guardian->status,
                $assessment->score,
                strtoupper($band),
                $assessment->requiresReasonToApprove() ? 'reason needed' : '',
                implode('; ', $assessment->reasons) ?: '(nothing flagged)',
            ];
        }

        $this->table(
            ['#', 'Name', 'Relation', 'Kind', 'Status', 'Pts', 'Band', 'Approval', 'Why'],
            $rows,
        );

        $this->newLine();
        $this->line('Bands across all '.$guardians->count().' trusted contacts:');

        foreach ($bands as $band => $count) {
            $this->line(sprintf('  %-8s %d', strtoupper($band), $count));
        }

        /*
         * ⚠️ A DISTRIBUTION WITH ONLY ONE BAND IN IT IS THE FINDING.
         *
         * If everybody scores the same, the scorer is not discriminating and
         * the number is decoration — a reviewer would learn nothing from it and
         * would stop looking. Said here rather than left for somebody to notice
         * in a table of thirty identical rows.
         */
        if (count($bands) === 1) {
            $this->newLine();
            $this->error('Every single contact landed in the same band.');
            $this->line('A score that never varies tells a reviewer nothing and will be ignored.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Read the Why column. The question is not whether the arithmetic is right.');
        $this->line('It is whether a reviewer who did not write these rules would act correctly.');

        return self::SUCCESS;
    }
}
