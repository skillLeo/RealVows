<?php

namespace App\Console\Commands;

use App\Models\Guardian;
use App\Models\Photo;
use App\Models\Verification;
use App\Services\Photos\PhotoStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * ⚠️ ASKS REAL STORAGE WHETHER THE FILES WE PROMISE ACTUALLY EXIST.
 *
 * ── Why a command and not a test ──────────────────────────────────────────
 *
 * The suite runs against a faked disk, on purpose. It can prove the RULE — a
 * record naming a file should only exist when the file does — and it can never
 * prove the live server obeys it, because the live server is the one place the
 * suite cannot look.
 *
 * ⚠️ AND THE LIVE SERVER IS WHERE IT BROKE. Twenty-two guardian records pointed
 * at `guardians/demo/together.jpg` and nothing had ever written it. That
 * photograph is the primary check in the guardian system: a reviewer approves a
 * trusted contact by looking at the two of them holding a code issued minutes
 * before. Every one of those reviews threw, on the only screen that check
 * happens on, and the seeder had reported success — a path in a column looks
 * exactly like a photograph until somebody opens it.
 *
 * ⚠️ IT REPORTS, IT NEVER REPAIRS. Deleting a row whose file is missing would
 * destroy the evidence of whatever lost the file, and inventing a placeholder
 * would turn a visible fault into a plausible-looking one. Same rule as the
 * ledger check.
 *
 * Exit code 1 when anything is missing, so a scheduler or a deploy step can act
 * on it rather than a person needing to read the output.
 */
class CheckStoredFiles extends Command
{
    protected $signature = 'realvows:check-stored-files {--limit=15 : How many examples to print}';

    protected $description = 'Prove every stored file path points at a file that exists.';

    public function handle(): int
    {
        $disk = Storage::disk(PhotoStorageService::DISK);

        /**
         * ⚠️ EVERY COLUMN THAT NAMES A FILE. A new one added elsewhere and not
         * added here is unchecked, which is precisely how the joint photograph
         * went unnoticed. EveryStoredPathHasAFileTest asserts these columns
         * still exist so a rename cannot silently empty this list.
         *
         * @var array<string, array{model: class-string, fields: list<string>}>
         */
        $sources = [
            'member photographs' => ['model' => Photo::class, 'fields' => ['path', 'webp_path', 'thumbnail_path']],
            'verification selfies' => ['model' => Verification::class, 'fields' => ['selfie_path']],
            'trusted contact joint photographs' => ['model' => Guardian::class, 'fields' => ['joint_photo_path']],
        ];

        $checked = 0;
        $missing = [];

        foreach ($sources as $label => $source) {
            $rowsChecked = 0;
            $rowsMissing = 0;

            foreach ($source['model']::query()->cursor() as $row) {
                foreach ($source['fields'] as $field) {
                    $path = $row->{$field};

                    // A null path is a record saying "there is no file", which
                    // is honest and is not what this looks for.
                    if ($path === null || $path === '') {
                        continue;
                    }

                    $checked++;
                    $rowsChecked++;

                    if (! $disk->exists($path)) {
                        $rowsMissing++;
                        $missing[] = "{$label} · {$source['model']}#{$row->id}.{$field} → {$path}";
                    }
                }
            }

            $this->line(sprintf(
                '  %-38s %4d checked, %s',
                $label,
                $rowsChecked,
                $rowsMissing === 0 ? 'all present' : "{$rowsMissing} MISSING",
            ));
        }

        /*
         * ⚠️ NOTHING TO CHECK IS NOT A PASS. An empty database, a wrong disk, or
         * a model that stopped returning rows all produce zero findings, which
         * is the same output as a healthy system.
         */
        if ($checked === 0) {
            $this->error('No stored paths were examined at all, so this proves nothing.');
            $this->line('Either the database is empty or this is pointed at the wrong disk: '.PhotoStorageService::DISK);

            return self::FAILURE;
        }

        if ($missing === []) {
            $this->newLine();
            $this->info("Every one of the {$checked} stored paths points at a file that exists.");

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');

        $this->newLine();
        $this->error(count($missing).' of '.$checked.' stored paths point at a file that does not exist.');
        $this->line('Anything that serves one of these throws, and the person who finds out is');
        $this->line('whoever clicks it. Reported rather than repaired: a missing file is evidence.');
        $this->newLine();

        foreach (array_slice($missing, 0, $limit) as $line) {
            $this->line('  '.$line);
        }

        if (count($missing) > $limit) {
            $this->line('  … and '.(count($missing) - $limit).' more.');
        }

        return self::FAILURE;
    }
}
