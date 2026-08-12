<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Risk\RiskNarrator;
use App\Services\Settings\SettingsService;
use App\Support\AiProviderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ THE BANNER ASKS THE CONTAINER. IT DOES NOT RE-DERIVE THE RULE.
 *
 * Same argument as the gateway banner. A banner that reads the settings table
 * and reasons its way to "the AI is live" is a second implementation of a
 * decision made elsewhere, and two implementations of one rule eventually
 * disagree. At that point the banner confidently reports a state the product is
 * not in, which is worse than having no banner.
 */
class AiProviderBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user->fresh();
    }

    /** With nothing configured, which is the state today. */
    public function test_it_reports_off_and_says_what_still_works(): void
    {
        $status = app(AiProviderStatus::class)->forDisplay();

        $this->assertFalse($status['live']);
        $this->assertNull($status['provider']);
        $this->assertTrue($status['riskFallsBackToRules']);
        $this->assertTrue($status['writerHidden']);
        $this->assertStringContainsString('Nothing is blocked', $status['note']);
    }

    /**
     * ⚠️ THE TEST THE BRIEF ASKS FOR: rebind the container and the banner must
     * change. If it does not, it is reading the settings table and reasoning,
     * which is the failure this whole design avoids.
     */
    public function test_rebinding_the_narrator_changes_what_the_banner_says(): void
    {
        $this->assertFalse(app(AiProviderStatus::class)->forDisplay()['live']);

        $this->app->bind(RiskNarrator::class, fn () => new class(app(SettingsService::class)) extends RiskNarrator
        {
            public function isConfigured(): bool
            {
                return true;
            }
        });

        $status = app(AiProviderStatus::class)->forDisplay();

        $this->assertTrue(
            $status['live'],
            'The banner did not change when the bound narrator did, so it is re-deriving the '
                .'rule from settings rather than asking the object that does the work.'
        );

        $this->assertFalse($status['writerHidden']);
        $this->assertNull($status['note']);
    }

    /**
     * ⚠️ AND IT IS NOT A CONSTANT. Without this, a banner hardcoded to `false`
     * would pass the first test and half of the second.
     */
    public function test_a_stored_key_alone_does_not_make_the_banner_lie(): void
    {
        app(SettingsService::class)->set('ai', 'openai_api_key', 'sk-a-real-looking-key');

        $status = app(AiProviderStatus::class)->forDisplay();

        $this->assertTrue($status['live']);
        $this->assertSame('openai', $status['provider']);
        $this->assertNotNull($status['riskModel']);
    }

    /** Members never learn which provider is bound. */
    public function test_the_banner_is_withheld_from_a_member(): void
    {
        $member = User::factory()->create();

        $body = html_entity_decode(
            $this->actingAs($member)->get('/dashboard')->getContent(),
            ENT_QUOTES | ENT_HTML5
        );

        $this->assertStringNotContainsString('riskFallsBackToRules', $body);
    }

    /** ⚠️ POSITIVE CONTROL: the owner does receive it. */
    public function test_the_owner_receives_the_banner(): void
    {
        $body = html_entity_decode(
            $this->actingAs($this->owner())->get('/admin/settings')->assertOk()->getContent(),
            ENT_QUOTES | ENT_HTML5
        );

        $this->assertStringContainsString(
            'riskFallsBackToRules',
            $body,
            'The owner did not receive the banner, so the member assertion proves nothing.'
        );
    }
}
