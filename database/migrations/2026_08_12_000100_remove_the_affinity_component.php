<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the affinity component from the product.
 *
 * ⚠️ THIS DELETES DATA AND IT IS NOT REVERSIBLE IN ANY USEFUL SENSE.
 *
 * `profiles.sign` and `profiles.element` were derived from date of birth, so
 * `down()` can recreate the columns but not the values. It does not try to:
 * recreating an empty column and calling that a rollback would be a lie in the
 * one place a person checks before running something frightening. Date of
 * birth is untouched, so the values are recomputable in principle, and the
 * code that computed them has been deleted, so in practice they are gone.
 *
 * ── ⚠️ THE STORED BREAKDOWN IS REWRITTEN, NOT DROPPED ─────────────────────
 *
 * `matches.score_breakdown` is a JSON column holding every component's raw,
 * weight and weighted value. The affinity key is stripped from each row rather
 * than the column being dropped, because the rest of that JSON is the audit
 * trail behind every score a member has already been shown.
 *
 * ⚠️ THE TOTALS ARE LEFT ALONE, AND THAT IS DELIBERATE.
 *
 * A stored score of 87 was shown to a member and possibly acted on. Recomputing
 * it under the new weights would silently change history: the admin match log
 * would show a number nobody ever saw, and a member asking "why did it say 87?"
 * would be told something different from what they were told. Existing matches
 * keep the score they were given. New matches are scored under the new weights.
 * The breakdown records which regime produced it.
 *
 * ── DRY RUN, AS REQUIRED BEFORE ANY DESTRUCTIVE MIGRATION ─────────────────
 *
 * Run `php artisan realvows:affinity-removal-dry-run` first. It reports how
 * many profiles carry a sign, how many breakdowns carry the key, and prints a
 * sample of the rewritten JSON without writing anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * ⚠️ CHUNKED. On shared hosting this ran against a live table with the
         * queue cron firing every minute. A single UPDATE over every row holds
         * a lock long enough for a member's page to time out.
         */
        DB::table('matches')
            ->select('id', 'score_breakdown')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $decoded = json_decode((string) $row->score_breakdown, true);

                    if (! is_array($decoded)) {
                        continue;
                    }

                    $changed = false;

                    foreach (['components', 'redistributed'] as $section) {
                        if (isset($decoded[$section]['affinity'])) {
                            unset($decoded[$section]['affinity']);
                            $changed = true;
                        }
                    }

                    if (isset($decoded['missing']) && is_array($decoded['missing'])) {
                        $filtered = array_values(array_filter(
                            $decoded['missing'],
                            fn ($k) => $k !== 'affinity',
                        ));

                        if ($filtered !== $decoded['missing']) {
                            $decoded['missing'] = $filtered;
                            $changed = true;
                        }
                    }

                    if (! $changed) {
                        continue;
                    }

                    // Marks which weighting regime produced this row, so a
                    // score from before the change is never mistaken for one
                    // computed under the new weights.
                    $decoded['weighting'] = 'pre_2026_08_12';

                    DB::table('matches')
                        ->where('id', $row->id)
                        ->update(['score_breakdown' => json_encode($decoded)]);
                }
            });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['sign', 'element']);
        });
    }

    public function down(): void
    {
        /*
         * ⚠️ THE COLUMNS COME BACK EMPTY AND THE BREAKDOWNS DO NOT COME BACK
         * AT ALL. Stated here rather than discovered at 3am by whoever ran
         * this expecting a rollback.
         */
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('sign', 16)->nullable();
            $table->string('element', 16)->nullable();
        });
    }
};
