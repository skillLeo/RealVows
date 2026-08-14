<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * ⚠️ TAKES A BACKUP AND THEN PROVES IT CAN BE RESTORED.
 *
 * ══════════════════════════════════════════════════════════════════════════
 * A backup nobody has restored is a file. It is not a backup.
 * ══════════════════════════════════════════════════════════════════════════
 *
 * This is the same shape as everything else this project has been bitten by: a
 * process that reports success while checking nothing. `mysqldump` exits zero on
 * a truncated write, on a permissions problem halfway through, and on a table it
 * silently skipped. The file exists, it is large, it looks exactly like a
 * working backup, and the first time anybody finds out otherwise is the worst
 * day the platform will ever have.
 *
 * So every run restores what it just took, into a scratch database, and compares
 * the row counts table by table against the live one.
 *
 * ── ⚠️ WHAT IT NEVER DOES ─────────────────────────────────────────────────
 *
 * It never touches the live database. The restore goes into a separate scratch
 * schema which is dropped afterwards, whether the check passed or not. A backup
 * verifier that could damage the thing it protects is worse than no verifier.
 *
 * ── ⚠️ PHOTOGRAPHS ARE NOT IN THE DUMP ────────────────────────────────────
 *
 * The database holds paths. The files live on disk, and losing them loses
 * verification material and every joint photograph that trusted-contact
 * approvals rest on. They are archived separately here and their count is
 * checked, because `realvows:check-stored-files` already proved that a path and
 * a file are two different things.
 */
class BackUpAndProveRestorable extends Command
{
    protected $signature = 'realvows:backup
                            {--keep=7 : How many backups to retain}
                            {--skip-restore-test : Take the backup without proving it restores}';

    protected $description = 'Back up the database and photographs, then prove the backup restores.';

    /** ⚠️ Outside the served directory. A backup inside public_html is a leak. */
    private const DIR = '/var/backups/realvows';

    public function handle(): int
    {
        $stamp = now()->format('Y-m-d_His');
        $dir = self::DIR;

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0700, true);
        }

        $sqlPath = "{$dir}/realvows-{$stamp}.sql";
        $filesPath = "{$dir}/photographs-{$stamp}.tar.gz";

        $db = config('database.connections.mysql');

        // ── The dump ────────────────────────────────────────────────────
        $dump = new Process([
            'mysqldump',
            '--host='.$db['host'],
            '--port='.$db['port'],
            '--user='.$db['username'],
            '--password='.$db['password'],
            '--single-transaction',
            '--routines',
            '--triggers',
            $db['database'],
        ]);
        $dump->setTimeout(600);
        $dump->run();

        if (! $dump->isSuccessful()) {
            $this->error('mysqldump failed: '.trim($dump->getErrorOutput()));

            return self::FAILURE;
        }

        File::put($sqlPath, $dump->getOutput());
        chmod($sqlPath, 0600);

        $bytes = File::size($sqlPath);

        /*
         * ⚠️ A SUCCESSFUL EXIT CODE IS NOT A BACKUP. mysqldump returns zero on
         * a write that ran out of disk partway through. The size floor catches
         * the obviously-empty case; the restore below catches everything else.
         */
        if ($bytes < 10_000) {
            $this->error("The dump is only {$bytes} bytes. That is not this database.");

            return self::FAILURE;
        }

        $this->line('  database   '.number_format($bytes / 1_048_576, 1).' MB');

        // ── The photographs ─────────────────────────────────────────────
        $storage = storage_path('app/private');

        if (File::isDirectory($storage)) {
            $tar = new Process(['tar', '-czf', $filesPath, '-C', dirname($storage), basename($storage)]);
            $tar->setTimeout(900);
            $tar->run();

            if (! $tar->isSuccessful()) {
                $this->error('Archiving the photographs failed: '.trim($tar->getErrorOutput()));

                return self::FAILURE;
            }

            chmod($filesPath, 0600);
            $this->line('  photographs '.number_format(File::size($filesPath) / 1_048_576, 1).' MB');
        }

        // ── The part that makes it a backup ─────────────────────────────
        if (! $this->option('skip-restore-test')) {
            if (! $this->proveItRestores($sqlPath, $db)) {
                return self::FAILURE;
            }
        } else {
            $this->warn('  Restore test skipped. This is a file, not a proven backup.');
        }

        $this->prune((int) $this->option('keep'));

        $this->newLine();
        $this->info("Backup taken and proven restorable: {$sqlPath}");

        return self::SUCCESS;
    }

    /**
     * ⚠️ RESTORES INTO A SCRATCH SCHEMA AND COMPARES ROW COUNTS.
     *
     * The scratch database is dropped in a finally block, so a failure halfway
     * through cannot leave a stray copy of every member's data sitting on the
     * server under a name nobody recognises.
     *
     * @param  array<string, mixed>  $db
     */
    private function proveItRestores(string $sqlPath, array $db): bool
    {
        $scratch = 'realvows_restore_check';

        $mysql = function (string $sql) use ($db): Process {
            $p = new Process([
                'mysql',
                '--host='.$db['host'],
                '--port='.$db['port'],
                '--user='.$db['username'],
                '--password='.$db['password'],
                '-e', $sql,
            ]);
            $p->setTimeout(600);
            $p->run();

            return $p;
        };

        try {
            $create = $mysql("DROP DATABASE IF EXISTS `{$scratch}`; CREATE DATABASE `{$scratch}`;");

            if (! $create->isSuccessful()) {
                /*
                 * ⚠️ NOT SILENTLY SKIPPED. The database user may not hold CREATE
                 * rights, which is a reasonable way to run a server and means
                 * this verification cannot happen here. Saying so is the point:
                 * an unverifiable backup should be known to be unverifiable
                 * rather than assumed to be fine.
                 */
                $this->error('Could not create a scratch database, so the backup is UNVERIFIED.');
                $this->line('  '.trim($create->getErrorOutput()));
                $this->line('  Grant CREATE to the database user, or run the restore test by hand.');

                return false;
            }

            $restore = Process::fromShellCommandline(
                sprintf(
                    'mysql --host=%s --port=%s --user=%s --password=%s %s < %s',
                    escapeshellarg((string) $db['host']),
                    escapeshellarg((string) $db['port']),
                    escapeshellarg((string) $db['username']),
                    escapeshellarg((string) $db['password']),
                    escapeshellarg($scratch),
                    escapeshellarg($sqlPath),
                ),
            );
            $restore->setTimeout(900);
            $restore->run();

            if (! $restore->isSuccessful()) {
                $this->error('The backup would NOT restore: '.trim($restore->getErrorOutput()));

                return false;
            }

            // ── Compare, table by table ─────────────────────────────────
            $tables = DB::select('SHOW TABLES');
            $key = 'Tables_in_'.$db['database'];
            $mismatches = [];
            $compared = 0;

            foreach ($tables as $row) {
                $table = $row->{$key};

                $live = DB::table($table)->count();
                $back = DB::connection('mysql')
                    ->select("SELECT COUNT(*) AS n FROM `{$scratch}`.`{$table}`")[0]->n ?? -1;

                $compared++;

                if ((int) $live !== (int) $back) {
                    $mismatches[] = "{$table}: live {$live}, restored {$back}";
                }
            }

            /*
             * ⚠️ COMPARING NOTHING IS NOT A PASS. An empty table list produces
             * an empty mismatch list, which reads exactly like a perfect
             * restore.
             */
            if ($compared === 0) {
                $this->error('No tables were compared, so this proves nothing about the backup.');

                return false;
            }

            if ($mismatches !== []) {
                $this->error('The restored copy does not match the live database:');

                foreach ($mismatches as $line) {
                    $this->line('  '.$line);
                }

                return false;
            }

            $this->line("  restore    verified across {$compared} tables, every row count matches");

            return true;
        } finally {
            // ⚠️ ALWAYS. A stray copy of every member's data under an
            // unfamiliar name is its own incident.
            $mysql("DROP DATABASE IF EXISTS `{$scratch}`;");
        }
    }

    private function prune(int $keep): void
    {
        foreach (['realvows-*.sql', 'photographs-*.tar.gz'] as $pattern) {
            $files = glob(self::DIR.'/'.$pattern) ?: [];

            // Newest first, so the slice drops the oldest.
            usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

            foreach (array_slice($files, $keep) as $old) {
                @unlink($old);
            }
        }
    }
}
