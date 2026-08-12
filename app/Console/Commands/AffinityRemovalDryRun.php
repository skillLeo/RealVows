<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the affinity-removal migration would do, without doing any of it.
 *
 * ⚠️ STANDING RULE 3: A DESTRUCTIVE MIGRATION IS DRY-RUN BEFORE IT IS RUN.
 *
 * The migration drops two columns and rewrites every stored score breakdown.
 * Both are irreversible in the way that matters — `down()` can recreate an
 * empty column and cannot recreate a value.
 *
 * ⚠️ IT COUNTS FROM THE DATA, NOT FROM THE SCHEMA. "There are 214 matches so
 * 214 breakdowns will change" is a guess dressed as a number. This decodes
 * every row and counts the ones that actually carry the key, because the
 * difference between those two figures is exactly the kind of thing that turns
 * out to matter after the fact.
 */
class AffinityRemovalDryRun extends Command
{
    protected $signature = 'realvows:affinity-removal-dry-run {--sample=2 : Rewritten breakdowns to print}';

    protected $description = 'Report what the affinity-removal migration would change, changing nothing';

    public function handle(): int
    {
        $this->line('');
        $this->info('── Dry run: removing the affinity component ──');
        $this->line('');

        if (! Schema::hasTable('profiles') || ! Schema::hasTable('matches')) {
            $this->error('profiles or matches table is missing. Is this the right database?');

            return self::FAILURE;
        }

        // ── profiles ──────────────────────────────────────────────────────
        $hasColumns = Schema::hasColumn('profiles', 'sign');

        if (! $hasColumns) {
            $this->warn('  profiles.sign and profiles.element are already gone. Nothing to drop.');
        } else {
            $total = DB::table('profiles')->count();
            $withSign = DB::table('profiles')->whereNotNull('sign')->count();

            $this->line(sprintf('  profiles                 %d rows', $total));
            $this->line(sprintf('  carrying a sign value    %d  ⚠️ these values are DELETED', $withSign));
        }

        // ── breakdowns ────────────────────────────────────────────────────
        $matches = 0;
        $carrying = 0;
        $samples = [];
        $sampleWanted = max(0, (int) $this->option('sample'));

        DB::table('matches')->select('id', 'score_breakdown')->orderBy('id')
            ->chunkById(200, function ($rows) use (&$matches, &$carrying, &$samples, $sampleWanted) {
                foreach ($rows as $row) {
                    $matches++;
                    $decoded = json_decode((string) $row->score_breakdown, true);

                    if (! is_array($decoded)) {
                        continue;
                    }

                    $hasKey = isset($decoded['components']['affinity'])
                        || isset($decoded['redistributed']['affinity'])
                        || in_array('affinity', $decoded['missing'] ?? [], true);

                    if (! $hasKey) {
                        continue;
                    }

                    $carrying++;

                    if (count($samples) < $sampleWanted) {
                        $before = $decoded;
                        unset($decoded['components']['affinity'], $decoded['redistributed']['affinity']);
                        $decoded['missing'] = array_values(array_filter(
                            $decoded['missing'] ?? [], fn ($k) => $k !== 'affinity'
                        ));
                        $decoded['weighting'] = 'pre_2026_08_12';

                        $samples[] = [
                            'id' => $row->id,
                            'total_before' => $before['total'] ?? null,
                            'total_after' => $decoded['total'] ?? null,
                            'components_before' => array_keys($before['components'] ?? []),
                            'components_after' => array_keys($decoded['components'] ?? []),
                        ];
                    }
                }
            });

        $this->line('');
        $this->line(sprintf('  matches                  %d rows', $matches));
        $this->line(sprintf('  breakdowns carrying it   %d  ⚠️ these are REWRITTEN', $carrying));
        $this->line('');

        foreach ($samples as $s) {
            $this->info("  ── match {$s['id']} ──");
            $this->line('    total stays        '.var_export($s['total_before'], true)
                .'  →  '.var_export($s['total_after'], true));
            $this->line('    components before  '.implode(', ', $s['components_before']));
            $this->line('    components after   '.implode(', ', $s['components_after']));
            $this->line('');
        }

        /*
         * ⚠️ THE TOTALS DO NOT MOVE, AND SOMEBODY WILL ASK WHY.
         */
        $this->warn('  Stored totals are NOT recomputed. A score of 87 was shown to a member and');
        $this->warn('  possibly acted on; recomputing it would put a number in the audit log that');
        $this->warn('  nobody ever saw. Existing matches keep their score and are tagged with the');
        $this->warn('  weighting that produced it. New matches use the new weights.');
        $this->line('');
        $this->info('  Nothing was changed. Run `php artisan migrate` to apply.');
        $this->line('');

        return self::SUCCESS;
    }
}
