<?php

namespace Database\Seeders;

use App\Models\CreditLedgerEntry;
use App\Models\Profile;
use App\Models\User;
use App\Models\Verification;
use App\Services\Photos\PhotoStorageService;
use App\Services\Profile\ProfileCompleteness;
use App\Models\MatchRecord;
use App\Models\Report;
use App\Services\Credits\CreditService;
use App\Services\Matching\MatchGenerator;
use App\Services\Payments\PurchaseService;
use App\Services\Sign\SignCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demonstration data.
 *
 * ⚠️ NEVER run this on production. Every account shares one obvious password
 * so the client can click through the product; the command refuses to run
 * outside local unless deliberately forced.
 *
 * The people here are not real. Portraits come from a service that exists to
 * supply demo faces, and the addendum's warning still stands: identifiable
 * photographs of real people cannot ship as fake members on a live platform.
 * Before launch these are replaced by genuine member uploads.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'realvows2026';

    private const DOMAIN = 'realvows.test';

    /** Slot 1 is their own portrait; the rest are scenes, never faces. */
    private const SCENE_SLOTS = ['full_length', 'social', 'open'];

    /** @var list<string> Seed images that were not found, reported at the end. */
    private array $skippedPhotos = [];

    public function __construct(
        private readonly PhotoStorageService $photos,
        private readonly ProfileCompleteness $completeness,
        private readonly SignCalculator $signs,
    ) {}

    public function run(): void
    {
        $this->clearPreviousDemoData();

        $admins = $this->createAdmins();

        // The real reviewer, not a hardcoded id — the wipe above renumbers
        // users on every run, so an assumed id points at nothing.
        $reviewer = $admins->firstWhere('email', 'reviewer@'.self::DOMAIN);

        $men = $this->createMembers('man', 15, $reviewer);
        $women = $this->createMembers('woman', 15, $reviewer);

        $this->createMatches($men, $women);
        $this->stageTheDemoAccounts($men, $women, $reviewer);

        $this->reportSkips();

        $this->command?->newLine();
        $this->command?->info('Demo data ready.');
        $this->command?->line('  admins  : '.$admins->count());
        $this->command?->line('  members : '.($men->count() + $women->count()));
        $this->command?->line('  password: '.self::PASSWORD);
    }

    private function clearPreviousDemoData(): void
    {
        $ids = User::withTrashed()->where('email', 'like', '%@'.self::DOMAIN)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $profileIds = Profile::whereIn('user_id', $ids)->pluck('id');

        DB::table('matches')->whereIn('seeker_profile_id', $profileIds)
            ->orWhereIn('matched_profile_id', $profileIds)->delete();
        DB::table('credit_ledger')->whereIn('user_id', $ids)->delete();
        DB::table('purchases')->whereIn('user_id', $ids)->delete();
        DB::table('verifications')->whereIn('profile_id', $profileIds)->delete();
        DB::table('photos')->whereIn('profile_id', $profileIds)->delete();
        DB::table('profile_preferences')->whereIn('profile_id', $profileIds)->delete();
        Profile::whereIn('user_id', $ids)->delete();
        User::withTrashed()->whereIn('id', $ids)->forceDelete();
    }

    private function createAdmins()
    {
        $rows = [
            ['name' => 'Umar', 'email' => 'admin@'.self::DOMAIN, 'role' => 'super_admin'],
            ['name' => 'Amina (reviewer)', 'email' => 'reviewer@'.self::DOMAIN, 'role' => 'admin'],
        ];

        return collect($rows)->map(function (array $row) {
            $user = User::create([
                'name' => $row['name'],
                'email' => $row['email'],
                'password' => Hash::make(self::PASSWORD),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($row['role']);

            return $user;
        });
    }

    private function createMembers(string $gender, int $count, ?User $reviewer = null)
    {
        $names = $gender === 'man' ? self::MEN : self::WOMEN;
        $prefix = $gender === 'man' ? 'boy' : 'girl';
        $seeking = $gender === 'man' ? 'woman' : 'man';

        $members = collect();

        for ($i = 0; $i < $count; $i++) {
            $person = $names[$i];
            $index = $i + 1;

            $user = User::create([
                'name' => $person['first'],
                'email' => "{$prefix}{$index}@".self::DOMAIN,
                'password' => Hash::make(self::PASSWORD),
                'phone_country_code' => $person['cc'],
                'phone_number' => $person['phone'],
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
                'last_login_at' => now()->subDays(random_int(0, 20)),
            ])->save();

            $user->assignRole('member');

            $dob = Carbon::parse($person['dob']);
            $derived = $this->signs->for($dob);

            $profile = Profile::create([
                'user_id' => $user->id,
                'first_name' => $person['first'],
                'last_name' => $person['last'],
                'date_of_birth' => $dob->toDateString(),
                'gender' => $gender,
                'seeking' => $seeking,
                'city' => $person['city'],
                'country' => $person['country'],
                'willing_to_relocate' => $person['relocate'],
                'nationality' => $person['country'],

                // Internal, never surfaced. Written here so the Phase 2 engine
                // has real values to score against when it is built.
                'sign' => $derived['sign'],
                'element' => $derived['element'],

                'height_cm' => $person['height'],
                'body_type' => $person['body'],
                'eye_colour' => $person['eyes'],
                'hair_colour' => $person['hair'],
                'has_tattoos' => false,
                'has_piercings' => $gender === 'woman',

                'education' => $person['education'],
                'occupation' => $person['occupation'],
                'industry' => $person['industry'],
                'religion' => $person['religion'],
                'faith_importance' => $person['faith'],
                'smoking' => 'never',
                'drinking' => $person['drinking'],
                'has_children' => 'none',
                'wants_children' => $person['wants_children'],
                'pets' => $person['pets'],
                'languages' => $person['languages'],

                'relationship_goal' => 'marriage_soon',
                'marriage_timeline' => $person['timeline'],
                'core_values' => $person['values'],
                'conflict_style' => $person['conflict'],
                'love_languages' => $person['love'],
                'bio' => $this->bio($person),
                'interests' => $person['interests'],
                'personality_scales' => [
                    'social' => $person['scales'][0],
                    'planning' => $person['scales'][1],
                    'decisions' => $person['scales'][2],
                    'outlook' => $person['scales'][3],
                ],
                'current_step' => 7,
                'submitted_at' => now()->subDays(random_int(1, 14)),
            ]);

            $profile->preferences()->create([
                'age_min' => $person['age_min'],
                'age_max' => $person['age_max'],
                'height_min_cm' => 150,
                'height_max_cm' => 200,
                'body_types' => ['slim', 'athletic', 'average', 'curvy'],
                'distance_preference' => $person['distance'],
                'deal_breakers' => $person['deal_breakers'],
                'preferred_qualities' => $person['qualities'],
            ]);

            $this->attachPhotos($profile, $gender, $index);

            // Most are verified so the pool is populated. A few are left in
            // the queue and one is sent back, so the admin screens have real
            // work to show rather than an empty state.
            $status = match (true) {
                $index <= 12 => Verification::STATUS_VERIFIED,
                $index === 13 => Verification::STATUS_REJECTED,
                default => Verification::STATUS_PENDING,
            };

            $profile->verification()->create([
                'status' => $status,
                'code' => 'RV-'.strtoupper(Str::random(6)),
                'selfie_path' => null,
                'reviewed_by' => $status === Verification::STATUS_PENDING ? null : $reviewer?->id,
                'reviewed_at' => $status === Verification::STATUS_PENDING ? null : now()->subDays(random_int(1, 10)),
                'rejection_reason' => $status === Verification::STATUS_REJECTED
                    ? 'The note is not readable'
                    : null,
                'attempt_number' => $status === Verification::STATUS_REJECTED ? 2 : 1,
            ]);

            $assessment = $this->completeness->assess($profile->fresh(['photos', 'preferences']));
            $profile->forceFill(['completeness_percent' => $assessment['percent']])->save();

            $members->push($profile->fresh());
        }

        return $members;
    }

    /**
     * Four photos per member, run through the real upload pipeline so the
     * WebP renditions, thumbnails and EXIF stripping are all genuinely
     * exercised rather than faked by copying files into place.
     */
    private function attachPhotos(Profile $profile, string $gender, int $index): void
    {
        $portrait = storage_path("app/seed/portraits/{$gender}-{$index}.jpg");

        if (is_file($portrait)) {
            // store() returns the stored file's metadata; the slot is ours to
            // add, or every row lands on the enum's first value and collides.
            $profile->photos()->create([
                ...$this->photos->store($this->asUpload($portrait), $profile->id, 'passport'),
                'slot' => 'passport',
            ]);
        } else {
            $this->recordSkip($portrait);
        }

        foreach (self::SCENE_SLOTS as $offset => $slot) {
            // A distinct scene per member per slot, so no two profiles share
            // a photograph — a duplicate set reads as "the whole thing is fake".
            $sceneNumber = (($index - 1) * 3 + $offset + ($gender === 'man' ? 0 : 20)) % 40 + 1;
            $scene = storage_path("app/seed/scenes/scene-{$sceneNumber}.jpg");

            if (is_file($scene)) {
                $profile->photos()->create([
                    ...$this->photos->store($this->asUpload($scene), $profile->id, $slot),
                    'slot' => $slot,
                ]);
            } else {
                $this->recordSkip($scene);
            }
        }
    }

    /**
     * ⚠️ A GUARD THAT SKIPS MUST BE LOUD.
     *
     * This exact `is_file()` produced thirty members with zero photographs on
     * the live server and said nothing — `storage/app/seed/` is gitignored, so
     * a clean deploy has no source images and every check quietly failed. The
     * seeder reported success and the product looked healthy and empty.
     *
     * Counted here and shouted about at the end of the run, because a silent
     * skip is how an empty product reaches production looking fine.
     */
    private function recordSkip(string $path): void
    {
        $this->skippedPhotos[] = $path;

        Log::warning('DemoSeeder: seed image missing, photo not created.', ['path' => $path]);
    }

    /** Fails the seed rather than letting a photoless demo look successful. */
    private function reportSkips(): void
    {
        if ($this->skippedPhotos === []) {
            return;
        }

        $count = count($this->skippedPhotos);
        $example = $this->skippedPhotos[0];

        $this->command?->newLine();
        $this->command?->error("  {$count} seed images were missing — those photos do not exist.");
        $this->command?->line("  First missing: {$example}");
        $this->command?->line('  storage/app/seed/ is gitignored. Sync it (see DEPLOY.md step 6) and re-run.');

        throw new \RuntimeException(
            "DemoSeeder skipped {$count} photographs because their source files are missing. "
            .'Sync storage/app/seed/ to this environment and run the seeder again. '
            .'A demo with no photographs looks like a broken product.'
        );
    }

    private function asUpload(string $path): UploadedFile
    {
        // Copied first: the service reads and re-encodes, and the seed
        // originals must survive for the next run.
        $temp = sys_get_temp_dir().'/'.Str::random(16).'.jpg';
        copy($path, $temp);

        return new UploadedFile($temp, basename($path), 'image/jpeg', null, true);
    }


    /**
     * ⚠️ EVERY STATE REACHABLE FROM THE LOGIN PANEL, WITHOUT BUYING OR
     * WAITING FOR ANYTHING.
     *
     * The client will click through this once, on a phone, probably while
     * travelling. If seeing the empty state requires creating an account and
     * waiting for a cron, he will never see it — so each numbered account is
     * staged into a different state deliberately.
     *
     *   Girl 1  verified, ZERO credits, sealed introductions   the hook
     *   Girl 2  verified, credits in hand, nothing opened      before the ceremony
     *   Girl 3  verified, three opened, purchase history       the payoff
     *   Girl 4  awaiting verification                          the waiting state
     *   Girl 5  verified, nothing above the threshold          the honest empty state
     *   Boy 1   mirrors Girl 1                                 the other side
     */
    private function stageTheDemoAccounts($men, $women, ?User $reviewer): void
    {
        $girl = fn (int $n) => $women[$n - 1] ?? null;
        $boy = fn (int $n) => $men[$n - 1] ?? null;

        // ── Girl 1 and Boy 1: sealed introductions, no credits ───────────
        // Nothing to do but make sure they have none. This is the state the
        // whole commercial engine rests on.
        foreach ([$girl(1), $boy(1)] as $profile) {
            $this->setCredits($profile, 0);
        }

        // ── Girl 2: credits in hand, nothing opened yet ──────────────────
        $this->buyFor($girl(2), 'triple');

        // ── Girl 3: three opened, with a receipt behind them ─────────────
        $this->buyFor($girl(3), 'octet');
        $this->openTopMatches($girl(3), 3);
        $this->buyFor($girl(3), 'single');

        // ── Girl 4: still waiting to be checked ──────────────────────────
        if ($girl(4)) {
            $girl(4)->verification?->forceFill([
                'status' => Verification::STATUS_PENDING,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ])->save();

            // An unverified profile is not matchable, so its introductions go.
            MatchRecord::where('seeker_profile_id', $girl(4)->id)->delete();
        }

        // ── Girl 5: verified, but nobody clears the bar ──────────────────
        // The honest empty state. Achieved by removing her introductions
        // rather than by lowering anybody's score.
        if ($girl(5)) {
            MatchRecord::where('seeker_profile_id', $girl(5)->id)->delete();
            $this->setCredits($girl(5), 0);
        }

        // One report already in the queue, so that screen is not empty when
        // he opens it.
        $this->seedOneReport($girl(3), $boy(2));

        $this->command?->line('  demo states : girl1 sealed · girl2 credits · girl3 opened · girl4 waiting · girl5 empty');
    }

    /** Buys through the real PurchaseService and FakeGateway. */
    private function buyFor(?Profile $profile, string $package): void
    {
        $user = $profile ? User::find($profile->user_id) : null;

        if (! $user) {
            return;
        }

        $purchases = app(PurchaseService::class);
        $purchases->settle($purchases->initiate($user, $package));
    }

    private function openTopMatches(?Profile $profile, int $count): void
    {
        $user = $profile ? User::find($profile->user_id) : null;

        if (! $user) {
            return;
        }

        $credits = app(CreditService::class);

        MatchRecord::where('seeker_profile_id', $profile->id)
            ->where('status', MatchRecord::STATUS_TEASER)
            ->orderByDesc('total_score')
            ->limit($count)
            ->get()
            ->each(function (MatchRecord $match) use ($credits, $user) {
                if ($user->fresh()->creditBalance() > 0) {
                    $credits->unlockMatch($user->fresh(), $match);
                }
            });
    }

    /** Levels a balance to an exact figure through the real ledger. */
    private function setCredits(?Profile $profile, int $target): void
    {
        $user = $profile ? User::find($profile->user_id) : null;

        if (! $user) {
            return;
        }

        $delta = $target - $user->creditBalance();

        if ($delta === 0) {
            return;
        }

        app(CreditService::class)->adjust(
            $user,
            $delta,
            $delta > 0 ? 'goodwill' : 'admin_adjustment',
            'Demonstration data staging',
            $user,
        );
    }

    private function seedOneReport(?Profile $reporter, ?Profile $subject): void
    {
        if (! $reporter || ! $subject) {
            return;
        }

        Report::firstOrCreate([
            'reporter_profile_id' => $reporter->id,
            'subject_profile_id' => $subject->id,
            'status' => Report::STATUS_OPEN,
        ], [
            'reason' => 'appears_to_be_scam',
            'note' => 'They asked me to move the conversation off the platform straight away.',
        ]);
    }

    /**
     * Introductions, produced by running the REAL matching engine.
     *
     * ⚠️ Deliberately not hand-written rows any more.
     *
     * Seeded data written by direct insert drifts from what the engine
     * actually does the moment a scorer changes, and then the demonstration
     * quietly stops representing the product. Running the real generator
     * means this seeder is a demo and an integration test at the same time:
     * if the engine breaks, seeding fails loudly rather than producing
     * plausible-looking fiction.
     */
    private function createMatches($men, $women): void
    {
        $generator = app(MatchGenerator::class);
        $written = 0;

        // Both directions, because a match row is directional and the two
        // scores are independent — preferences are not symmetric.
        foreach ($men->concat($women) as $profile) {
            $written += $generator->generateFor($profile->fresh(['preferences', 'verification']));
        }

        $this->command?->line("  introductions: {$written} (generated by the engine)");
    }

    /**
     * Purchase history, produced by running the REAL checkout.
     *
     * Same reasoning as the matches: these go through PurchaseService and
     * FakeGateway rather than a direct insert, so the ledger rows, the
     * balance_after chain and the purchase statuses are all exactly what a
     * real payment produces. `realvows:verify-ledger` passes on this data
     * because it was made the same way.
     */
    private function createPurchaseHistory($profiles): void
    {
        $purchases = app(PurchaseService::class);
        $keys = ['single', 'triple', 'octet'];

        foreach ($profiles->values() as $i => $profile) {
            $user = User::find($profile->user_id);

            if (! $user) {
                continue;
            }

            $purchase = $purchases->initiate($user, $keys[$i % 3]);

            // Straight to settlement. FakeGateway approves deterministically,
            // and settle() is the same method the live callback calls.
            $purchases->settle($purchase);
        }
    }

    /**
     * Opens a few introductions, again through the real service, so the
     * client sees both states side by side on the demonstration build.
     */
    private function openSomeMatches($profiles): void
    {
        $credits = app(CreditService::class);

        foreach ($profiles as $profile) {
            $user = User::find($profile->user_id);

            if (! $user || $user->creditBalance() < 1) {
                continue;
            }

            $match = MatchRecord::where('seeker_profile_id', $profile->id)
                ->where('status', MatchRecord::STATUS_TEASER)
                ->orderByDesc('total_score')
                ->first();

            if ($match) {
                $credits->unlockMatch($user, $match);
            }
        }
    }

    private function bio(array $person): string
    {
        // Comfortably over the 150-word floor the builder enforces, so every
        // seeded profile reads as genuinely complete.
        return implode(' ', [
            "I am {$person['occupation']} and I have been doing it long enough now to know it suits me.",
            'Most weeks look fairly quiet from the outside. I cook properly at least a few evenings, I see my family on the weekend, and I try to be in bed before it gets silly.',
            "I grew up in {$person['city']} and I still think it is one of the better places in the world to be from, though I am open about where we would eventually settle.",
            'What I am looking for is not complicated. Someone kind, someone who says what they mean, and someone who treats marriage as something you build rather than something that simply happens to you.',
            'I would rather have one honest conversation than three polite ones, and I would rather sort out a disagreement the same evening than let it sit.',
            'Faith matters in my house, and I would like it to matter in ours, though I care much more about how someone lives than about how they describe themselves.',
            'I am hoping to meet someone who wants children eventually, who has their own life and their own friends, and who is genuinely ready for this rather than curious about it.',
            'If that sounds like you, I would like to hear from you.',
        ]);
    }

    private const MEN = [
        ['first' => 'Andre', 'last' => 'Campbell', 'dob' => '1992-04-11', 'city' => 'Kingston', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5510101', 'height' => 181, 'body' => 'athletic', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'a civil engineer', 'industry' => 'Construction', 'religion' => 'Christianity', 'faith' => 'important', 'drinking' => 'socially', 'wants_children' => 'yes', 'pets' => ['dog'], 'languages' => ['English', 'Patois'], 'timeline' => 'within_year', 'values' => ['family', 'honesty', 'faith', 'loyalty', 'stability'], 'conflict' => 'discuss_now', 'love' => ['time', 'acts'], 'interests' => ['Cricket', 'Cooking', 'Hiking'], 'scales' => [65, 40, 55, 35], 'age_min' => 25, 'age_max' => 34, 'distance' => 'same_country', 'deal_breakers' => ['smoking'], 'qualities' => ['Kind', 'Direct'], 'relocate' => true],
        ['first' => 'Yusuf', 'last' => 'Rahman', 'dob' => '1990-09-02', 'city' => 'Lahore', 'country' => 'Pakistan', 'cc' => '+92', 'phone' => '300 5510102', 'height' => 176, 'body' => 'average', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Master's degree", 'occupation' => 'a secondary school teacher', 'industry' => 'Education', 'religion' => 'Islam', 'faith' => 'essential', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['none'], 'languages' => ['Urdu', 'English'], 'timeline' => 'within_year', 'values' => ['faith', 'family', 'kindness', 'education', 'honesty'], 'conflict' => 'seek_compromise', 'love' => ['words', 'time'], 'interests' => ['Reading', 'Calligraphy', 'Football'], 'scales' => [40, 70, 45, 25], 'age_min' => 24, 'age_max' => 32, 'distance' => 'anywhere', 'deal_breakers' => ['drinking', 'different_faith'], 'qualities' => ['Patient', 'Faithful'], 'relocate' => true],
        ['first' => 'Marcus', 'last' => 'Bennett', 'dob' => '1988-12-19', 'city' => 'Montego Bay', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5510103', 'height' => 185, 'body' => 'athletic', 'eyes' => 'Hazel', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'a hotel operations manager', 'industry' => 'Hospitality', 'religion' => 'Christianity', 'faith' => 'somewhat', 'drinking' => 'socially', 'wants_children' => 'open', 'pets' => ['dog', 'cat'], 'languages' => ['English'], 'timeline' => 'one_to_two', 'values' => ['honesty', 'ambition', 'humour', 'loyalty', 'health'], 'conflict' => 'discuss_now', 'love' => ['acts', 'touch'], 'interests' => ['Running', 'Music', 'Travel'], 'scales' => [80, 35, 60, 65], 'age_min' => 26, 'age_max' => 36, 'distance' => 'same_city', 'deal_breakers' => [], 'qualities' => ['Warm', 'Independent'], 'relocate' => false],
        ['first' => 'Imran', 'last' => 'Sheikh', 'dob' => '1993-06-30', 'city' => 'Dubai', 'country' => 'United Arab Emirates', 'cc' => '+971', 'phone' => '50 5510104', 'height' => 178, 'body' => 'slim', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'an accountant', 'industry' => 'Finance', 'religion' => 'Islam', 'faith' => 'essential', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['none'], 'languages' => ['Urdu', 'English', 'Arabic'], 'timeline' => 'within_year', 'values' => ['faith', 'family', 'stability', 'honesty', 'generosity'], 'conflict' => 'need_space', 'love' => ['acts', 'gifts'], 'interests' => ['Chess', 'Cycling', 'Cooking'], 'scales' => [35, 75, 70, 20], 'age_min' => 23, 'age_max' => 31, 'distance' => 'anywhere', 'deal_breakers' => ['drinking', 'smoking'], 'qualities' => ['Calm', 'Devout'], 'relocate' => true],
        ['first' => 'Damion', 'last' => 'Thomas', 'dob' => '1991-02-14', 'city' => 'Spanish Town', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5510105', 'height' => 179, 'body' => 'average', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => 'Diploma', 'occupation' => 'an electrician', 'industry' => 'Trades', 'religion' => 'Christianity', 'faith' => 'important', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['dog'], 'languages' => ['English', 'Patois'], 'timeline' => 'within_year', 'values' => ['family', 'loyalty', 'faith', 'kindness', 'community'], 'conflict' => 'seek_compromise', 'love' => ['acts', 'time'], 'interests' => ['Football', 'Church', 'Fishing'], 'scales' => [55, 50, 40, 30], 'age_min' => 24, 'age_max' => 33, 'distance' => 'same_country', 'deal_breakers' => ['smoking'], 'qualities' => ['Steady', 'Family-minded'], 'relocate' => false],
        ['first' => 'Tariq', 'last' => 'Hussain', 'dob' => '1989-08-08', 'city' => 'London', 'country' => 'United Kingdom', 'cc' => '+44', 'phone' => '7700 551106', 'height' => 183, 'body' => 'athletic', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Master's degree", 'occupation' => 'a software developer', 'industry' => 'Technology', 'religion' => 'Islam', 'faith' => 'important', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['cat'], 'languages' => ['English', 'Urdu'], 'timeline' => 'one_to_two', 'values' => ['honesty', 'faith', 'ambition', 'family', 'independence'], 'conflict' => 'discuss_now', 'love' => ['words', 'acts'], 'interests' => ['Cricket', 'Photography', 'Cooking'], 'scales' => [45, 65, 75, 45], 'age_min' => 25, 'age_max' => 34, 'distance' => 'anywhere', 'deal_breakers' => ['drinking'], 'qualities' => ['Thoughtful', 'Ambitious'], 'relocate' => true],
        ['first' => 'Kemar', 'last' => 'Reid', 'dob' => '1994-11-23', 'city' => 'Kingston', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5510107', 'height' => 174, 'body' => 'slim', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'a graphic designer', 'industry' => 'Creative', 'religion' => 'Christianity', 'faith' => 'somewhat', 'drinking' => 'socially', 'wants_children' => 'open', 'pets' => ['none'], 'languages' => ['English'], 'timeline' => 'two_plus', 'values' => ['humour', 'honesty', 'adventure', 'kindness', 'independence'], 'conflict' => 'need_space', 'love' => ['time', 'words'], 'interests' => ['Art', 'Music', 'Beach'], 'scales' => [70, 30, 50, 75], 'age_min' => 23, 'age_max' => 31, 'distance' => 'same_city', 'deal_breakers' => [], 'qualities' => ['Creative', 'Easy-going'], 'relocate' => false],
        ['first' => 'Bilal', 'last' => 'Chaudhry', 'dob' => '1990-03-05', 'city' => 'Toronto', 'country' => 'Canada', 'cc' => '+1', 'phone' => '416 5510108', 'height' => 180, 'body' => 'average', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'a pharmacist', 'industry' => 'Healthcare', 'religion' => 'Islam', 'faith' => 'essential', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['none'], 'languages' => ['English', 'Punjabi'], 'timeline' => 'within_year', 'values' => ['faith', 'family', 'health', 'honesty', 'stability'], 'conflict' => 'seek_compromise', 'love' => ['acts', 'time'], 'interests' => ['Badminton', 'Cooking', 'Volunteering'], 'scales' => [50, 70, 60, 25], 'age_min' => 24, 'age_max' => 32, 'distance' => 'anywhere', 'deal_breakers' => ['drinking', 'different_faith'], 'qualities' => ['Reliable', 'Generous'], 'relocate' => true],
        ['first' => 'Shane', 'last' => 'Grant', 'dob' => '1987-07-17', 'city' => 'Ocho Rios', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5510109', 'height' => 188, 'body' => 'athletic', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => 'Diploma', 'occupation' => 'a dive instructor', 'industry' => 'Tourism', 'religion' => 'Christianity', 'faith' => 'somewhat', 'drinking' => 'socially', 'wants_children' => 'open', 'pets' => ['dog'], 'languages' => ['English', 'Patois', 'Spanish'], 'timeline' => 'one_to_two', 'values' => ['adventure', 'health', 'honesty', 'humour', 'community'], 'conflict' => 'discuss_now', 'love' => ['touch', 'time'], 'interests' => ['Diving', 'Surfing', 'Travel'], 'scales' => [85, 25, 45, 70], 'age_min' => 25, 'age_max' => 36, 'distance' => 'same_country', 'deal_breakers' => ['smoking'], 'qualities' => ['Adventurous', 'Warm'], 'relocate' => false],
        ['first' => 'Omar', 'last' => 'Farooq', 'dob' => '1992-01-28', 'city' => 'Karachi', 'country' => 'Pakistan', 'cc' => '+92', 'phone' => '300 5510110', 'height' => 177, 'body' => 'average', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Master's degree", 'occupation' => 'a marketing manager', 'industry' => 'Marketing', 'religion' => 'Islam', 'faith' => 'important', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['cat'], 'languages' => ['Urdu', 'English'], 'timeline' => 'within_year', 'values' => ['family', 'faith', 'ambition', 'loyalty', 'humour'], 'conflict' => 'discuss_now', 'love' => ['words', 'gifts'], 'interests' => ['Cricket', 'Films', 'Food'], 'scales' => [75, 45, 55, 40], 'age_min' => 23, 'age_max' => 31, 'distance' => 'anywhere', 'deal_breakers' => ['drinking'], 'qualities' => ['Funny', 'Loyal'], 'relocate' => true],
        ['first' => 'Rohan', 'last' => 'Persaud', 'dob' => '1991-05-09', 'city' => 'Georgetown', 'country' => 'Guyana', 'cc' => '+1', 'phone' => '592 5510111', 'height' => 175, 'body' => 'slim', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'an agricultural officer', 'industry' => 'Agriculture', 'religion' => 'Hinduism', 'faith' => 'important', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['dog'], 'languages' => ['English'], 'timeline' => 'within_year', 'values' => ['family', 'tradition', 'honesty', 'stability', 'community'], 'conflict' => 'seek_compromise', 'love' => ['acts', 'time'], 'interests' => ['Gardening', 'Cricket', 'Cooking'], 'scales' => [40, 65, 50, 30], 'age_min' => 24, 'age_max' => 32, 'distance' => 'same_country', 'deal_breakers' => ['smoking'], 'qualities' => ['Grounded', 'Traditional'], 'relocate' => false],
        ['first' => 'Jamal', 'last' => 'Lewis', 'dob' => '1989-10-12', 'city' => 'Kingston', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5510112', 'height' => 182, 'body' => 'average', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Master's degree", 'occupation' => 'a lawyer', 'industry' => 'Legal', 'religion' => 'Christianity', 'faith' => 'important', 'drinking' => 'socially', 'wants_children' => 'yes', 'pets' => ['none'], 'languages' => ['English'], 'timeline' => 'within_year', 'values' => ['honesty', 'ambition', 'family', 'education', 'loyalty'], 'conflict' => 'discuss_now', 'love' => ['time', 'words'], 'interests' => ['Debating', 'Golf', 'Reading'], 'scales' => [60, 70, 80, 45], 'age_min' => 26, 'age_max' => 35, 'distance' => 'same_country', 'deal_breakers' => [], 'qualities' => ['Sharp', 'Committed'], 'relocate' => true],
        ['first' => 'Nasir', 'last' => 'Ali', 'dob' => '1993-09-21', 'city' => 'Birmingham', 'country' => 'United Kingdom', 'cc' => '+44', 'phone' => '7700 551113', 'height' => 173, 'body' => 'slim', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'a physiotherapist', 'industry' => 'Healthcare', 'religion' => 'Islam', 'faith' => 'essential', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['none'], 'languages' => ['English', 'Urdu'], 'timeline' => 'one_to_two', 'values' => ['faith', 'health', 'family', 'kindness', 'honesty'], 'conflict' => 'need_space', 'love' => ['acts', 'touch'], 'interests' => ['Football', 'Fitness', 'Cooking'], 'scales' => [45, 60, 55, 25], 'age_min' => 23, 'age_max' => 30, 'distance' => 'same_country', 'deal_breakers' => ['drinking', 'different_faith'], 'qualities' => ['Caring', 'Fit'], 'relocate' => false],
        ['first' => 'Devon', 'last' => 'Clarke', 'dob' => '1986-04-03', 'city' => 'Portmore', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5510114', 'height' => 186, 'body' => 'athletic', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => 'Diploma', 'occupation' => 'a logistics coordinator', 'industry' => 'Logistics', 'religion' => 'Christianity', 'faith' => 'somewhat', 'drinking' => 'socially', 'wants_children' => 'open', 'pets' => ['dog'], 'languages' => ['English', 'Patois'], 'timeline' => 'two_plus', 'values' => ['humour', 'loyalty', 'honesty', 'stability', 'health'], 'conflict' => 'avoid', 'love' => ['touch', 'acts'], 'interests' => ['Basketball', 'Cars', 'Music'], 'scales' => [70, 40, 50, 55], 'age_min' => 26, 'age_max' => 38, 'distance' => 'same_city', 'deal_breakers' => [], 'qualities' => ['Relaxed', 'Dependable'], 'relocate' => false],
        ['first' => 'Hamza', 'last' => 'Iqbal', 'dob' => '1994-02-27', 'city' => 'Islamabad', 'country' => 'Pakistan', 'cc' => '+92', 'phone' => '300 5510115', 'height' => 179, 'body' => 'average', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'an architect', 'industry' => 'Architecture', 'religion' => 'Islam', 'faith' => 'important', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['none'], 'languages' => ['Urdu', 'English'], 'timeline' => 'within_year', 'values' => ['faith', 'education', 'family', 'ambition', 'kindness'], 'conflict' => 'seek_compromise', 'love' => ['words', 'time'], 'interests' => ['Sketching', 'Hiking', 'Coffee'], 'scales' => [50, 70, 65, 40], 'age_min' => 22, 'age_max' => 30, 'distance' => 'anywhere', 'deal_breakers' => ['drinking'], 'qualities' => ['Curious', 'Considerate'], 'relocate' => true],
    ];

    private const WOMEN = [
        ['first' => 'Zainab', 'last' => 'Khan', 'dob' => '1997-03-16', 'city' => 'Kingston', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5520201', 'height' => 165, 'body' => 'slim', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'a primary school teacher', 'industry' => 'Education', 'religion' => 'Islam', 'faith' => 'essential', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['cat'], 'languages' => ['English', 'Urdu'], 'timeline' => 'within_year', 'values' => ['faith', 'family', 'kindness', 'honesty', 'education'], 'conflict' => 'discuss_now', 'love' => ['time', 'words'], 'interests' => ['Baking', 'Reading', 'Gardening'], 'scales' => [45, 70, 50, 25], 'age_min' => 26, 'age_max' => 36, 'distance' => 'same_country', 'deal_breakers' => ['drinking', 'smoking'], 'qualities' => ['Patient', 'Faithful'], 'relocate' => true],
        ['first' => 'Shanice', 'last' => 'Brown', 'dob' => '1994-08-24', 'city' => 'Montego Bay', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5520202', 'height' => 170, 'body' => 'curvy', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'a nurse', 'industry' => 'Healthcare', 'religion' => 'Christianity', 'faith' => 'important', 'drinking' => 'socially', 'wants_children' => 'yes', 'pets' => ['dog'], 'languages' => ['English', 'Patois'], 'timeline' => 'within_year', 'values' => ['family', 'honesty', 'kindness', 'faith', 'health'], 'conflict' => 'discuss_now', 'love' => ['acts', 'touch'], 'interests' => ['Dancing', 'Cooking', 'Church'], 'scales' => [70, 55, 45, 40], 'age_min' => 27, 'age_max' => 38, 'distance' => 'same_country', 'deal_breakers' => ['smoking'], 'qualities' => ['Warm', 'Honest'], 'relocate' => false],
        ['first' => 'Aisha', 'last' => 'Malik', 'dob' => '1995-12-04', 'city' => 'Lahore', 'country' => 'Pakistan', 'cc' => '+92', 'phone' => '300 5520203', 'height' => 162, 'body' => 'slim', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Master's degree", 'occupation' => 'a university lecturer', 'industry' => 'Education', 'religion' => 'Islam', 'faith' => 'essential', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['none'], 'languages' => ['Urdu', 'English'], 'timeline' => 'within_year', 'values' => ['faith', 'education', 'family', 'honesty', 'kindness'], 'conflict' => 'seek_compromise', 'love' => ['words', 'time'], 'interests' => ['Reading', 'Calligraphy', 'Tea'], 'scales' => [35, 75, 60, 20], 'age_min' => 27, 'age_max' => 36, 'distance' => 'anywhere', 'deal_breakers' => ['drinking', 'different_faith'], 'qualities' => ['Thoughtful', 'Devout'], 'relocate' => true],
        ['first' => 'Kimberly', 'last' => 'Henry', 'dob' => '1993-06-11', 'city' => 'Kingston', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5520204', 'height' => 168, 'body' => 'average', 'eyes' => 'Hazel', 'hair' => 'Brown', 'education' => "Bachelor's degree", 'occupation' => 'an accountant', 'industry' => 'Finance', 'religion' => 'Christianity', 'faith' => 'important', 'drinking' => 'socially', 'wants_children' => 'yes', 'pets' => ['none'], 'languages' => ['English'], 'timeline' => 'one_to_two', 'values' => ['stability', 'honesty', 'family', 'ambition', 'loyalty'], 'conflict' => 'need_space', 'love' => ['acts', 'gifts'], 'interests' => ['Yoga', 'Travel', 'Films'], 'scales' => [50, 80, 70, 50], 'age_min' => 28, 'age_max' => 38, 'distance' => 'same_city', 'deal_breakers' => ['smoking'], 'qualities' => ['Organised', 'Kind'], 'relocate' => false],
        ['first' => 'Fatima', 'last' => 'Siddiqui', 'dob' => '1996-01-19', 'city' => 'Dubai', 'country' => 'United Arab Emirates', 'cc' => '+971', 'phone' => '50 5520205', 'height' => 160, 'body' => 'slim', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'a dentist', 'industry' => 'Healthcare', 'religion' => 'Islam', 'faith' => 'essential', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['none'], 'languages' => ['Arabic', 'English', 'Urdu'], 'timeline' => 'within_year', 'values' => ['faith', 'family', 'health', 'generosity', 'honesty'], 'conflict' => 'seek_compromise', 'love' => ['acts', 'time'], 'interests' => ['Cooking', 'Swimming', 'Volunteering'], 'scales' => [40, 70, 55, 25], 'age_min' => 26, 'age_max' => 35, 'distance' => 'anywhere', 'deal_breakers' => ['drinking', 'smoking', 'different_faith'], 'qualities' => ['Generous', 'Calm'], 'relocate' => true],
        ['first' => 'Alicia', 'last' => 'Morgan', 'dob' => '1992-10-07', 'city' => 'Ocho Rios', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5520206', 'height' => 172, 'body' => 'athletic', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => 'Diploma', 'occupation' => 'a fitness coach', 'industry' => 'Sport', 'religion' => 'Christianity', 'faith' => 'somewhat', 'drinking' => 'socially', 'wants_children' => 'open', 'pets' => ['dog'], 'languages' => ['English', 'Patois'], 'timeline' => 'one_to_two', 'values' => ['health', 'humour', 'honesty', 'adventure', 'loyalty'], 'conflict' => 'discuss_now', 'love' => ['touch', 'time'], 'interests' => ['Running', 'Beach', 'Music'], 'scales' => [80, 40, 50, 65], 'age_min' => 27, 'age_max' => 38, 'distance' => 'same_country', 'deal_breakers' => ['smoking'], 'qualities' => ['Energetic', 'Loyal'], 'relocate' => false],
        ['first' => 'Nadia', 'last' => 'Ahmed', 'dob' => '1995-05-29', 'city' => 'London', 'country' => 'United Kingdom', 'cc' => '+44', 'phone' => '7700 552207', 'height' => 166, 'body' => 'average', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Master's degree", 'occupation' => 'a data analyst', 'industry' => 'Technology', 'religion' => 'Islam', 'faith' => 'important', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['cat'], 'languages' => ['English', 'Bengali'], 'timeline' => 'one_to_two', 'values' => ['independence', 'faith', 'honesty', 'education', 'family'], 'conflict' => 'discuss_now', 'love' => ['words', 'acts'], 'interests' => ['Photography', 'Cycling', 'Museums'], 'scales' => [45, 65, 75, 55], 'age_min' => 27, 'age_max' => 36, 'distance' => 'anywhere', 'deal_breakers' => ['drinking'], 'qualities' => ['Independent', 'Curious'], 'relocate' => true],
        ['first' => 'Tamara', 'last' => 'Wright', 'dob' => '1991-09-13', 'city' => 'Spanish Town', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5520208', 'height' => 164, 'body' => 'curvy', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'a social worker', 'industry' => 'Social care', 'religion' => 'Christianity', 'faith' => 'important', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['none'], 'languages' => ['English', 'Patois'], 'timeline' => 'within_year', 'values' => ['community', 'kindness', 'family', 'faith', 'honesty'], 'conflict' => 'seek_compromise', 'love' => ['acts', 'words'], 'interests' => ['Volunteering', 'Choir', 'Cooking'], 'scales' => [60, 60, 40, 35], 'age_min' => 28, 'age_max' => 40, 'distance' => 'same_country', 'deal_breakers' => ['drinking', 'smoking'], 'qualities' => ['Caring', 'Community-minded'], 'relocate' => false],
        ['first' => 'Sana', 'last' => 'Qureshi', 'dob' => '1996-07-02', 'city' => 'Toronto', 'country' => 'Canada', 'cc' => '+1', 'phone' => '416 5520209', 'height' => 163, 'body' => 'slim', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'a pharmacist', 'industry' => 'Healthcare', 'religion' => 'Islam', 'faith' => 'essential', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['none'], 'languages' => ['English', 'Punjabi'], 'timeline' => 'within_year', 'values' => ['faith', 'family', 'honesty', 'health', 'stability'], 'conflict' => 'need_space', 'love' => ['acts', 'time'], 'interests' => ['Baking', 'Badminton', 'Reading'], 'scales' => [40, 75, 60, 25], 'age_min' => 26, 'age_max' => 35, 'distance' => 'anywhere', 'deal_breakers' => ['drinking', 'different_faith'], 'qualities' => ['Steady', 'Warm'], 'relocate' => true],
        ['first' => 'Chantelle', 'last' => 'Gordon', 'dob' => '1994-04-21', 'city' => 'Portmore', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5520210', 'height' => 169, 'body' => 'average', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => 'Diploma', 'occupation' => 'a salon owner', 'industry' => 'Beauty', 'religion' => 'Christianity', 'faith' => 'somewhat', 'drinking' => 'socially', 'wants_children' => 'yes', 'pets' => ['cat'], 'languages' => ['English', 'Patois'], 'timeline' => 'one_to_two', 'values' => ['ambition', 'humour', 'family', 'loyalty', 'honesty'], 'conflict' => 'discuss_now', 'love' => ['gifts', 'touch'], 'interests' => ['Fashion', 'Music', 'Travel'], 'scales' => [85, 45, 45, 60], 'age_min' => 27, 'age_max' => 38, 'distance' => 'same_city', 'deal_breakers' => [], 'qualities' => ['Driven', 'Fun'], 'relocate' => false],
        ['first' => 'Maryam', 'last' => 'Yusuf', 'dob' => '1997-11-08', 'city' => 'Birmingham', 'country' => 'United Kingdom', 'cc' => '+44', 'phone' => '7700 552211', 'height' => 161, 'body' => 'slim', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'a speech therapist', 'industry' => 'Healthcare', 'religion' => 'Islam', 'faith' => 'essential', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['none'], 'languages' => ['English', 'Somali', 'Arabic'], 'timeline' => 'within_year', 'values' => ['faith', 'kindness', 'family', 'education', 'community'], 'conflict' => 'seek_compromise', 'love' => ['words', 'acts'], 'interests' => ['Reading', 'Walking', 'Cooking'], 'scales' => [45, 65, 50, 20], 'age_min' => 25, 'age_max' => 34, 'distance' => 'same_country', 'deal_breakers' => ['drinking', 'smoking', 'different_faith'], 'qualities' => ['Gentle', 'Principled'], 'relocate' => false],
        ['first' => 'Simone', 'last' => 'Barrett', 'dob' => '1990-02-17', 'city' => 'Kingston', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5520212', 'height' => 174, 'body' => 'athletic', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Master's degree", 'occupation' => 'a project manager', 'industry' => 'Consulting', 'religion' => 'Christianity', 'faith' => 'important', 'drinking' => 'socially', 'wants_children' => 'yes', 'pets' => ['dog'], 'languages' => ['English'], 'timeline' => 'within_year', 'values' => ['ambition', 'honesty', 'family', 'stability', 'independence'], 'conflict' => 'discuss_now', 'love' => ['time', 'acts'], 'interests' => ['Tennis', 'Reading', 'Wine'], 'scales' => [65, 80, 75, 55], 'age_min' => 29, 'age_max' => 40, 'distance' => 'same_country', 'deal_breakers' => ['smoking'], 'qualities' => ['Capable', 'Direct'], 'relocate' => true],
        ['first' => 'Priya', 'last' => 'Ramnarine', 'dob' => '1995-08-30', 'city' => 'Port of Spain', 'country' => 'Trinidad and Tobago', 'cc' => '+1', 'phone' => '868 5520213', 'height' => 158, 'body' => 'slim', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'a laboratory technician', 'industry' => 'Science', 'religion' => 'Hinduism', 'faith' => 'important', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['none'], 'languages' => ['English'], 'timeline' => 'within_year', 'values' => ['family', 'tradition', 'honesty', 'kindness', 'stability'], 'conflict' => 'need_space', 'love' => ['acts', 'time'], 'interests' => ['Cooking', 'Dance', 'Temple'], 'scales' => [40, 70, 55, 30], 'age_min' => 26, 'age_max' => 35, 'distance' => 'same_country', 'deal_breakers' => ['smoking'], 'qualities' => ['Traditional', 'Gentle'], 'relocate' => false],
        ['first' => 'Danielle', 'last' => 'Peters', 'dob' => '1993-03-25', 'city' => 'Negril', 'country' => 'Jamaica', 'cc' => '+1', 'phone' => '876 5520214', 'height' => 167, 'body' => 'average', 'eyes' => 'Green', 'hair' => 'Brown', 'education' => "Bachelor's degree", 'occupation' => 'a hotel event planner', 'industry' => 'Hospitality', 'religion' => 'Christianity', 'faith' => 'somewhat', 'drinking' => 'socially', 'wants_children' => 'open', 'pets' => ['dog', 'cat'], 'languages' => ['English', 'Spanish'], 'timeline' => 'two_plus', 'values' => ['adventure', 'humour', 'independence', 'honesty', 'health'], 'conflict' => 'avoid', 'love' => ['time', 'touch'], 'interests' => ['Sailing', 'Photography', 'Travel'], 'scales' => [75, 35, 55, 75], 'age_min' => 28, 'age_max' => 40, 'distance' => 'anywhere', 'deal_breakers' => [], 'qualities' => ['Free-spirited', 'Open'], 'relocate' => true],
        ['first' => 'Hafsa', 'last' => 'Noor', 'dob' => '1998-06-14', 'city' => 'Islamabad', 'country' => 'Pakistan', 'cc' => '+92', 'phone' => '300 5520215', 'height' => 159, 'body' => 'slim', 'eyes' => 'Brown', 'hair' => 'Black', 'education' => "Bachelor's degree", 'occupation' => 'a graphic designer', 'industry' => 'Creative', 'religion' => 'Islam', 'faith' => 'essential', 'drinking' => 'never', 'wants_children' => 'yes', 'pets' => ['cat'], 'languages' => ['Urdu', 'English'], 'timeline' => 'within_year', 'values' => ['faith', 'family', 'kindness', 'education', 'honesty'], 'conflict' => 'seek_compromise', 'love' => ['words', 'gifts'], 'interests' => ['Illustration', 'Poetry', 'Baking'], 'scales' => [40, 60, 45, 30], 'age_min' => 24, 'age_max' => 33, 'distance' => 'anywhere', 'deal_breakers' => ['drinking', 'different_faith'], 'qualities' => ['Creative', 'Sincere'], 'relocate' => true],
    ];
}
