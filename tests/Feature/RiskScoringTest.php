<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Profile;
use App\Models\User;
use App\Services\Risk\GuardianRiskScorer;
use App\Services\Risk\RiskAssessment;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ⚠️ THE MACHINE ADVISES. A HUMAN DECIDES.
 *
 * The brief requires that automated scoring cannot reject anybody, and the way
 * to guarantee that is to make rejection inexpressible rather than forbidden.
 */
class RiskScoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        Http::preventStrayRequests();
    }

    private function guardian(array $attributes = []): Guardian
    {
        $member = User::factory()->create(['email' => 'aisha@example.com']);
        Profile::factory()->woman()->create(['user_id' => $member->id]);

        return Guardian::create(array_merge([
            'member_id' => $member->id,
            'name' => 'Yusuf Adeyemi',
            'relationship' => 'father',
            'email' => 'yusuf@example.com',
            'status' => Guardian::STATUS_SUBMITTED,
            'joint_photo_path' => 'guardians/1/together.jpg',
            'user_id' => User::factory()->create()->id,
        ], $attributes));
    }

    /**
     * ⚠️ THE STRUCTURAL GUARANTEE. Not "we do not call reject" — there is
     * nothing to call.
     */
    public function test_a_risk_assessment_has_no_way_to_express_a_rejection(): void
    {
        $methods = get_class_methods(RiskAssessment::class);

        foreach (['reject', 'shouldReject', 'decide', 'verdict', 'approve', 'decision'] as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                $methods,
                "RiskAssessment::{$forbidden}() exists. Automated scoring must not be able to "
                    .'decide anything about a person.'
            );
        }

        // ⚠️ CONTROL: the assertion is capable of failing. If get_class_methods
        // returned nothing, every assertion above would pass vacuously.
        $this->assertContains('band', $methods);
        $this->assertContains('requiresReasonToApprove', $methods);
    }

    /**
     * ⚠️ EVEN THE WORST SCORE STILL PERMITS APPROVAL. It asks for a reason,
     * which is the difference between flagging and deciding.
     */
    public function test_the_highest_band_still_allows_a_human_to_approve(): void
    {
        $assessment = new RiskAssessment(score: 100, reasons: ['everything']);

        $this->assertSame(RiskAssessment::BAND_HIGH, $assessment->band());
        $this->assertTrue($assessment->requiresReasonToApprove());
    }

    public function test_the_bands_match_the_thresholds(): void
    {
        foreach ([0 => 'low', 29 => 'low', 30 => 'review', 60 => 'review', 61 => 'high', 100 => 'high'] as $score => $band) {
            $this->assertSame(
                $band,
                (new RiskAssessment($score, []))->band(),
                "A score of {$score} should be [{$band}]."
            );
        }
    }

    public function test_a_complete_trusted_contact_scores_low(): void
    {
        $assessment = app(GuardianRiskScorer::class)->score($this->guardian());

        $this->assertSame(RiskAssessment::BAND_LOW, $assessment->band());
        $this->assertSame([], $assessment->reasons);
    }

    /** ⚠️ POSITIVE CONTROL: the scorer can see a real problem. */
    public function test_a_missing_photograph_and_no_account_scores_high(): void
    {
        $assessment = app(GuardianRiskScorer::class)->score($this->guardian([
            'joint_photo_path' => null,
            'user_id' => null,
        ]));

        $this->assertSame(75, $assessment->score);
        $this->assertSame(RiskAssessment::BAND_HIGH, $assessment->band());
        $this->assertCount(2, $assessment->reasons);
    }

    /**
     * ⚠️ A CONTACT INVITED YESTERDAY IS NOT THE RISKIEST THING ON THE QUEUE.
     *
     * Found by reading a full run of real scores rather than by any test. Both
     * signals fired regardless of whether the person had been given the chance:
     * fifty points for a photograph nobody had asked them for, twenty-five for
     * not signing in to an account that did not exist yet. Seventy-five. HIGH.
     *
     * So every brand-new invitation looked like the most dangerous record in
     * the system. A reviewer opens it, finds nothing wrong, and learns within a
     * week that HIGH means nothing — the same failure as an alert nobody reads,
     * arriving through a number instead of an email.
     */
    public function test_a_freshly_invited_contact_is_not_penalised_for_being_early(): void
    {
        $assessment = app(GuardianRiskScorer::class)->score($this->guardian([
            'status' => Guardian::STATUS_INVITED,
            'joint_photo_path' => null,
            'user_id' => null,
        ]));

        $this->assertSame(0, $assessment->score, implode("\n", [
            'A contact invited yesterday scored for a photograph they cannot upload and an',
            'account they have not been asked to make yet. That measures elapsed time, not risk.',
        ]));
        $this->assertSame(RiskAssessment::BAND_LOW, $assessment->band());
        $this->assertSame([], $assessment->reasons);
    }

    public function test_a_registered_contact_is_given_room_to_upload(): void
    {
        // They have just made their account and are on the way to the upload
        // screen. Absent is the expected state, not a finding.
        $assessment = app(GuardianRiskScorer::class)->score($this->guardian([
            'status' => Guardian::STATUS_REGISTERED,
            'joint_photo_path' => null,
        ]));

        $this->assertSame(0, $assessment->score);
    }

    /**
     * ⚠️ THE CONTROL WITHOUT WHICH THE TWO ABOVE ARE INDISTINGUISHABLE FROM
     * DELETING THE SIGNAL.
     *
     * Narrowing when a rule fires is one edit away from switching it off, and
     * the demo data contains no verified-without-photograph case, so a full run
     * would look identical either way. This is the case that must still score,
     * and it is the most serious one in the whole scorer: somebody was approved
     * without the check that approval is supposed to rest on.
     */
    public function test_a_verified_contact_with_no_photograph_still_scores(): void
    {
        $assessment = app(GuardianRiskScorer::class)->score($this->guardian([
            'status' => Guardian::STATUS_VERIFIED,
            'joint_photo_path' => null,
        ]));

        $this->assertSame(50, $assessment->score, 'The photograph signal has been switched off rather than narrowed.');
        $this->assertContains('No joint photograph has been uploaded.', $assessment->reasons);
    }

    /** ⚠️ The strongest signal: one device operating both accounts. */
    public function test_the_same_device_as_the_member_is_scored(): void
    {
        $guardian = $this->guardian();
        $fingerprint = hash('sha256', 'one-device');

        $guardian->forceFill(['device_fingerprint_hash' => $fingerprint])->save();
        User::where('id', $guardian->member_id)->update(['device_fingerprint_hash' => $fingerprint]);

        $assessment = app(GuardianRiskScorer::class)->score($guardian->fresh());

        $this->assertGreaterThanOrEqual(40, $assessment->score);
        $this->assertStringContainsString('same device', implode(' ', $assessment->reasons));
    }

    public function test_a_score_is_never_above_one_hundred(): void
    {
        $guardian = $this->guardian(['joint_photo_path' => null, 'user_id' => null]);
        $guardian->forceFill([
            'risk_reasons' => ['a', 'b', 'c'],
            'email' => 'aisha@other.com',
        ])->save();

        $this->assertLessThanOrEqual(100, app(GuardianRiskScorer::class)->score($guardian->fresh())->score);
    }

    /**
     * ⚠️ NO KEY MEANS RULES ONLY, NOT AN ERROR. Both AI features must degrade
     * cleanly when nothing is configured, which is the state today.
     */
    public function test_with_no_api_key_the_score_still_works_and_nothing_is_called(): void
    {
        $assessment = app(GuardianRiskScorer::class)->score(
            $this->guardian(['joint_photo_path' => null])
        );

        $this->assertSame(50, $assessment->score);
        $this->assertNull($assessment->summary);

        // preventStrayRequests would fail the test if anything reached out.
        $this->assertTrue(true);
    }

    /**
     * ⚠️ AN OUTAGE MUST NOT BLOCK A MEMBER. A provider returning 500 produces a
     * score with a note, not an exception.
     */
    public function test_a_failing_provider_degrades_to_rules_only(): void
    {
        app(SettingsService::class)->set('ai', 'openai_api_key', 'sk-test-key');
        Http::fake(['api.openai.com/*' => Http::response('upstream error', 500)]);

        $assessment = app(GuardianRiskScorer::class)->score(
            $this->guardian(['joint_photo_path' => null])
        );

        $this->assertSame(50, $assessment->score, 'The rules stopped working when the model did.');
        $this->assertTrue($assessment->modelUnavailable);
        $this->assertNull($assessment->summary);
    }

    /**
     * ⚠️ NO PHOTOGRAPH IS EVER SENT TO A MODEL, and no surname either.
     */
    public function test_no_photograph_or_surname_is_sent_to_the_provider(): void
    {
        app(SettingsService::class)->set('ai', 'openai_api_key', 'sk-test-key');

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"summary":"Nothing unusual."}']]],
            ]),
        ]);

        app(GuardianRiskScorer::class)->score($this->guardian());

        Http::assertSent(function ($request) {
            $body = json_encode($request->data());

            $this->assertStringNotContainsString('joint_photo', $body, 'A photograph path was sent.');
            $this->assertStringNotContainsString('guardians/', $body, 'A storage path was sent.');
            $this->assertStringNotContainsString('Adeyemi', $body, 'A surname was sent.');
            $this->assertStringNotContainsString('aisha@example.com', $body, 'An email was sent.');

            return true;
        });
    }

    /**
     * ⚠️ THE MODEL MUST NEVER RESTATE OR REINTERPRET A FLAG.
     *
     * With a live key, given "signed in from the same device as the member",
     * the narrator wrote: "automated flags suggesting a close relationship with
     * the member."
     *
     * That is the OPPOSITE of what the signal means. Same device suggests one
     * person operating both accounts, which is the strongest fraud indicator in
     * this system, and a reviewer reading "close relationship" would approve.
     *
     * ⚠️ NO TEST COULD HAVE FOUND THAT. It was a true, fluent, harmless-looking
     * sentence, produced by a working feature, and it was dangerous. It was
     * found by reading the live output — which is the whole argument for reading
     * a model's output before real members depend on it.
     *
     * What a test CAN hold is the instruction. If somebody loosens the prompt
     * back into asking for a summary, paraphrase returns and so does this.
     */
    public function test_the_prompt_forbids_reinterpreting_a_flag(): void
    {
        $source = file_get_contents(base_path('app/Services/Risk/RiskNarrator.php'));

        foreach ([
            'DO NOT restate' => 'the instruction not to restate the flags',
            'soften' => 'the instruction not to soften them',
            'reinterpret' => 'the instruction not to reinterpret them',
            'CHECK next' => 'the instruction to name what to check instead',
            'empty summary' => 'permission to say nothing when there is nothing to add',
        ] as $needle => $what) {
            $this->assertStringContainsString(
                $needle,
                $source,
                "The risk narrator prompt has lost {$what}. Asked to summarise, the model "
                    .'paraphrases, and paraphrasing a fraud signal is how "same device" became '
                    .'"a close relationship".',
            );
        }

        // ⚠️ And it must not have gone back to asking for a summary of them.
        $this->assertStringNotContainsString(
            '"summary": "two sentences at most"',
            $source,
            'The prompt asks for a summary of the flags again.',
        );
    }
}
