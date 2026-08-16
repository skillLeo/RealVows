<?php

namespace Tests\Feature;

use App\Mail\IntroductionDelivered;
use App\Mail\IntroductionsUnlocked;
use App\Mail\MatchWithdrawn;
use App\Mail\MutualInterest;
use App\Mail\OperationalAlert;
use App\Mail\PurchaseReceipt;
use App\Mail\SomeoneInterested;
use App\Mail\NameYourTrustedContact;
use App\Mail\TeaserExpiring;
use App\Mail\TrustedContactInvitation;
use App\Mail\TrustedContactReminder;
use App\Mail\VerificationApproved;
use App\Mail\VerificationRejected;
use App\Models\Guardian;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Verification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\Scanners;
use Tests\TestCase;

/**
 * ⚠️ EVERY EMAIL, RENDERED, CHECKED FOR THE THREE THINGS THAT CANNOT BE
 * RECALLED ONCE SENT.
 *
 * ── Why this exists alongside MailableContentTest ─────────────────────────
 *
 * That file renders five mailables and checks them thoroughly. There are
 * TWELVE. The seven it does not reach include every trusted-contact message —
 * the newest writing in the product, sent to people who never signed up on
 * their own initiative, and therefore the most likely place for a description
 * of the verification method to have been written helpfully.
 *
 * ⚠️ AND THE FIVE ARE A HAND-WRITTEN LIST. A hand-written list of the things to
 * check is the same defect as a hand-written list of settings groups: the next
 * mailable is not on it, nobody notices, and the gap is invisible precisely
 * because everything on the list passes. So the classes are ENUMERATED FROM THE
 * DIRECTORY here, and a class with no fixture fails the run rather than being
 * skipped.
 *
 * ── ⚠️ WHAT IS CHECKED, AND WHY EACH IS UNRECOVERABLE ─────────────────────
 *
 * · No image of any kind. Not a photograph, not a logo, not a tracking pixel.
 *   An image in an email is a request to a server made by the mail client, and
 *   an <img> whose src is a signed photo route is the whole modesty rule
 *   defeated by a preview pane.
 *
 * · No description of the verification method. The method only works while it
 *   is not known in advance, and an email is forwarded, quoted and screenshotted
 *   far more freely than a page is.
 *
 * · No banned vocabulary. The client's own complaint, and email is where it
 *   would survive longest — nobody re-reads a template after it ships.
 */
class EveryEmailIsAuditedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Words that would describe HOW verification works rather than THAT it
     * happened.
     *
     * ⚠️ "verified" AND "verification" ARE DELIBERATELY ABSENT. Telling a
     * member they are verified is the entire point of one of these emails. The
     * rule is about the mechanism, not the fact, and a reader that could not
     * tell them apart would be turned off within a week.
     */
    private const METHOD_WORDS = [
        'handwritten', 'hand-written', 'write the code', 'write this code',
        'hold the code', 'holding the code', 'selfie with', 'piece of paper',
        'on a note', 'six-digit', 'six digit', 'code we issued', 'code on the note',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    // ─────────────────────────────────────────────────────────────────────

    public function test_every_mailable_in_the_application_has_a_fixture_here(): void
    {
        /*
         * ⚠️ THE ENUMERATION IS THE TEST. Every assertion below is about
         * absence, and the cheapest way to have no findings is to look at
         * nothing. A new mailable must fail HERE, loudly, rather than quietly
         * never being rendered.
         */
        $onDisk = [];

        foreach (File::files(app_path('Mail')) as $file) {
            $class = 'App\\Mail\\'.$file->getFilenameWithoutExtension();

            // The abstract parent is not a message anybody receives.
            if ((new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $onDisk[] = $class;
        }

        sort($onDisk);

        $built = array_keys($this->everyMailable());
        sort($built);

        $this->assertNotEmpty($onDisk, 'No mailables were found on disk at all.');

        $this->assertSame($onDisk, $built, implode("\n", [
            'The set of mailables built by this test no longer matches the set that exists.',
            'A mailable with no fixture here is never rendered and never audited, which is',
            'how seven of twelve went unchecked before this file existed.',
        ]));
    }

    public function test_no_email_contains_an_image_of_any_kind(): void
    {
        $offences = [];

        foreach ($this->renderAll() as $name => $html) {
            if (preg_match('/<img\b/i', $html)) {
                $offences[] = "{$name} has an <img> tag";
            }

            // A CSS background is an image request too, and it would not be
            // caught by looking for a tag.
            if (preg_match('/background-image\s*:/i', $html)) {
                $offences[] = "{$name} has a CSS background-image";
            }

            foreach (Scanners::photoPaths($html) as $path) {
                $offences[] = "{$name} → {$path}";
            }
        }

        $this->assertSame([], $offences, implode("\n  ", array_merge([
            'These emails carry an image. Mail clients fetch images before a person opens',
            'anything, and a signed photograph route in an <img> is the modesty rule defeated',
            'by a preview pane.',
        ], $offences)));
    }

    public function test_no_email_describes_the_verification_method(): void
    {
        $offences = [];

        foreach ($this->renderAll() as $name => $html) {
            $text = strtolower(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));

            foreach (self::METHOD_WORDS as $phrase) {
                if (str_contains($text, $phrase)) {
                    $offences[] = "{$name} → “{$phrase}”";
                }
            }
        }

        $this->assertSame([], $offences, implode("\n  ", array_merge([
            'These emails describe how verification is done. The method works only while it',
            'is not known in advance, and an email is forwarded and screenshotted far more',
            'freely than a page is.',
        ], $offences)));
    }

    public function test_no_email_uses_the_banned_vocabulary(): void
    {
        /*
         * ⚠️ OVER RENDERED OUTPUT, NOT SOURCE. ForbiddenLanguageTest reads the
         * templates, which is correct and cannot see a word that arrives
         * through a variable — a subject line built from a setting, a rejection
         * reason typed by a reviewer, a package label from the database.
         */
        $offences = [];

        foreach ($this->renderAll() as $name => $html) {
            $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

            foreach (Scanners::bannedVocabulary($text) as $word) {
                $offences[] = "{$name} → {$word}";
            }
        }

        $this->assertSame([], $offences, implode("\n  ", array_merge([
            'These emails use vocabulary the client has asked twice to be rid of.',
        ], $offences)));
    }

    /**
     * ⚠️ POSITIVE CONTROL, AND ALL THREE READERS ARE RUN.
     *
     * Every assertion above is an empty-array check over a scanner. A scanner
     * that has stopped matching produces exactly the same clean result as
     * twelve clean emails, and there is no way to tell them apart from the
     * outside.
     */
    public function test_the_three_readers_can_still_see_a_violation(): void
    {
        $bad = '<p>Hold the code we issued on a note.</p>'
            .'<img src="/photos/12?signature=abc">'
            .'<p>Your journey with us is seamless.</p>';

        // The image reader.
        $this->assertMatchesRegularExpression('/<img\b/i', $bad);
        $this->assertNotEmpty(Scanners::photoPaths($bad));

        // The method reader.
        $method = array_values(array_filter(
            self::METHOD_WORDS,
            fn ($p) => str_contains(strtolower($bad), $p),
        ));
        $this->assertNotEmpty($method, 'The verification-method reader is blind.');

        // The vocabulary reader.
        $this->assertNotEmpty(Scanners::bannedVocabulary($bad));

        /*
         * ⚠️ AND THE OTHER DIRECTION. A reader that flagged everything would
         * pass all four assertions above while being exactly as useless.
         */
        $clean = '<p>Someone new is waiting for you. Sign in when you have a moment.</p>';

        $this->assertDoesNotMatchRegularExpression('/<img\b/i', $clean);
        $this->assertSame([], Scanners::photoPaths($clean));
        $this->assertSame([], Scanners::bannedVocabulary($clean));
        $this->assertSame([], array_values(array_filter(
            self::METHOD_WORDS,
            fn ($p) => str_contains(strtolower($clean), $p),
        )));
    }

    // ─────────────────────────────────────────────────────────────────────

    /** @return array<string, string> */
    private function renderAll(): array
    {
        $rendered = [];

        foreach ($this->everyMailable() as $class => $mailable) {
            $name = class_basename($class);

            // The subject is part of what is sent and is the field most often
            // built from data, so it is audited with the body rather than
            // treated as metadata.
            $rendered[$name] = $mailable->envelope()->subject.' '.$mailable->render();
        }

        return $rendered;
    }

    /** @return array<class-string, Mailable> */
    private function everyMailable(): array
    {
        $user = $this->member();
        $guardian = $this->guardian($user);

        return [
            IntroductionDelivered::class => new IntroductionDelivered(
                $user,
                count: 1,
                score: 87,
                rationale: 'You are both settled in Kingston and you each named family and '
                    .'faith as the things that matter most.',
                firstNameOfMatch: 'Omar',
            ),
            IntroductionsUnlocked::class => new IntroductionsUnlocked($user, count: 3),
            MatchWithdrawn::class => new MatchWithdrawn('Aisha', 'Omar'),
            MutualInterest::class => new MutualInterest($user, otherFirstName: 'Omar'),
            /*
             * ⚠️ AN OPERATIONAL ALERT, AND IT IS AUDITED LIKE THE REST. It goes
             * to whoever runs the platform rather than to a member, and every
             * rule here still applies: no image a mail client will fetch, no
             * description of the verification method in a message that gets
             * forwarded, and none of the vocabulary the client asked twice to
             * be rid of.
             */
            OperationalAlert::class => new OperationalAlert(
                subjectLine: 'RealVows: something failed quietly',
                intro: 'Some things failed without anybody being told.',
                sections: ['Failed background jobs' => ['1 job failed and stopped retrying.']],
                outro: 'This message is only sent when something needs a person.',
            ),
            PurchaseReceipt::class => new PurchaseReceipt($this->purchase($user)),
            SomeoneInterested::class => new SomeoneInterested($user),
            NameYourTrustedContact::class => new NameYourTrustedContact($user, daysWaiting: 3),
            TeaserExpiring::class => new TeaserExpiring($user, count: 2, daysLeft: 7),
            TrustedContactInvitation::class => new TrustedContactInvitation($guardian, 'a-token'),
            TrustedContactReminder::class => new TrustedContactReminder($guardian, 'a-token', isFinal: false),
            VerificationApproved::class => new VerificationApproved($this->verification($user)),
            VerificationRejected::class => new VerificationRejected(
                $this->verification($user),
                reason: 'The note is not readable',
                note: 'Please try again in better light.',
            ),
        ];
    }

    private function member(): User
    {
        $user = User::factory()->create(['email' => 'aisha@example.com']);

        Profile::factory()->woman()->create([
            'user_id' => $user->id,
            'first_name' => 'Aisha',
            'last_name' => 'Mohammed-Bakare',
        ]);

        return $user->fresh();
    }

    private function guardian(User $member): Guardian
    {
        return Guardian::create([
            'member_id' => $member->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'email' => 'yusuf@example.com',
            'status' => Guardian::STATUS_INVITED,
            'is_appointed' => false,
        ]);
    }

    private function verification(User $member): Verification
    {
        return Verification::create([
            'profile_id' => $member->profile->id,
            'status' => Verification::STATUS_VERIFIED,
            'code' => 'AB12CD',
            'verified_at' => now(),
        ]);
    }

    private function purchase(User $member): Purchase
    {
        return Purchase::create([
            'user_id' => $member->id,
            'package_key' => 'single',
            'credits_granted' => 1,
            'amount_cents' => 2000,
            'currency' => 'USD',
            'status' => Purchase::STATUS_COMPLETED,
            'order_identifier' => (string) Str::uuid(),
            'completed_at' => now(),
        ]);
    }
}
