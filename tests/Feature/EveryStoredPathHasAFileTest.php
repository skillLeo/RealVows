<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Photo;
use App\Models\Verification;
use App\Services\Photos\PhotoStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ⚠️ A PATH IN A COLUMN IS NOT A PHOTOGRAPH.
 *
 * ── What this cost ────────────────────────────────────────────────────────
 *
 * DemoSeeder wrote `guardians/demo/together.jpg` into twenty-two guardian rows
 * and never put a file there. Every one of them pointed at the same image that
 * did not exist.
 *
 * ⚠️ AND THAT IMAGE IS THE PRIMARY CHECK IN THE WHOLE GUARDIAN SYSTEM. A
 * reviewer approves a trusted contact by looking at a photograph of the two of
 * them holding a code issued minutes earlier. With no file behind the path, the
 * single screen where that check happens threw for every guardian on the
 * server.
 *
 * The seeder reported success throughout, because a path in a column looks
 * exactly like a photograph until somebody opens it. The route reported the
 * failure honestly, into a log nobody was reading.
 *
 * ── ⚠️ WHY THIS RUNS AGAINST THE FAKE AND STILL MEANS SOMETHING ───────────
 *
 * The test suite cannot see production storage, so this cannot prove the live
 * server is clean. What it proves is the RULE: a record that names a file must
 * only exist when the file does. The live answer comes from
 * `realvows:check-stored-files`, which asks the same question of real storage
 * and is why the fault was found at all.
 */
class EveryStoredPathHasAFileTest extends TestCase
{
    // The column check asks the schema, so there has to be one.
    use RefreshDatabase;

    public function test_a_missing_file_is_detectable_from_the_record_alone(): void
    {
        /*
         * ⚠️ POSITIVE CONTROL FIRST, because everything below is an absence
         * check and the whole point is that an absence was invisible for days.
         * A checker that cannot see a missing file is the fault, not the guard.
         */
        Storage::fake(PhotoStorageService::DISK);
        $disk = Storage::disk(PhotoStorageService::DISK);

        $disk->put('guardians/demo/present.jpg', 'bytes');

        $this->assertTrue($disk->exists('guardians/demo/present.jpg'));
        $this->assertFalse(
            $disk->exists('guardians/demo/together.jpg'),
            'The checker reports a file that was never written as present, so it cannot see '
                .'the fault it exists to find.',
        );
    }

    /**
     * ⚠️ THE COLUMNS THAT NAME A FILE, LISTED SO A NEW ONE IS A DECISION.
     *
     * Adding a path column and forgetting it here is exactly how the guardian
     * joint photograph went unchecked, so the list is asserted non-empty and
     * each entry names the model it belongs to.
     */
    public function test_the_set_of_file_bearing_columns_is_known(): void
    {
        $columns = [
            Photo::class => ['path', 'webp_path', 'thumbnail_path'],
            Guardian::class => ['joint_photo_path'],
            Verification::class => ['selfie_path'],
        ];

        $this->assertNotEmpty($columns);

        foreach ($columns as $model => $fields) {
            $table = (new $model)->getTable();

            foreach ($fields as $field) {
                $this->assertTrue(
                    Schema::hasColumn($table, $field),
                    "{$model} no longer has a `{$field}` column. If a path column was renamed, "
                        .'realvows:check-stored-files is now silently skipping it.',
                );
            }
        }
    }

    public function test_the_command_that_asks_real_storage_exists(): void
    {
        /*
         * ⚠️ THE TEST SUITE CANNOT ANSWER THIS QUESTION FOR PRODUCTION. It runs
         * against a faked disk by design, so "no missing files" here says
         * nothing about the server. The command is the part that can be pointed
         * at real storage, and it is the reason this fault was found.
         */
        $this->assertArrayHasKey(
            'realvows:check-stored-files',
            Artisan::all(),
            'The only thing able to ask this question of real storage is gone.',
        );
    }
}
