<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PowerTranzGateway;
use App\Support\GatewayStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ "WHICH GATEWAY AM I ON?" MUST BE ANSWERABLE FROM THE SCREEN.
 *
 * Real-versus-fake is never chosen. PaymentServiceProvider infers it: the
 * stand-in binds when no `api_key` is stored and the environment is local or
 * demo, and PowerTranz binds otherwise. That inference is correct and it was
 * invisible — the settings screen showed a Gateway dropdown reading
 * "PowerTranz" while every checkout was handled by a fake we wrote.
 *
 * State that is true but unstated is the failure class that has caught this
 * project four times. On a screen that handles money it is not acceptable at
 * all, which is why the banner names the BOUND CLASS and not a setting.
 */
class GatewayBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    /**
     * ⚠️ THE CHECK THIS CLASS EXISTS FOR: the banner reports the class the
     * container actually handed out, not a setting that describes intent.
     */
    public function test_the_banner_names_the_actually_bound_gateway(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => new FakeGateway);

        $status = app(GatewayStatus::class)->forDisplay();

        $this->assertSame('FakeGateway', $status['className']);
        $this->assertTrue($status['isFake']);
        $this->assertFalse($status['isLive']);
        $this->assertStringContainsString('no real payments', $status['label']);
    }

    /**
     * ⚠️ IT MUST NOT RE-DERIVE THE BINDING RULE.
     *
     * A banner that reimplements "fake when no api_key" is right until
     * somebody edits the provider, and then confidently wrong — which is
     * worse than no banner at all. Rebinding the container must change what
     * it reports, which is only true if it asks rather than guesses.
     */
    public function test_the_banner_follows_the_container_rather_than_guessing(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => new FakeGateway);
        $this->assertTrue(app(GatewayStatus::class)->isFake());

        // Same settings, different binding — the report must change.
        $this->app->bind(PaymentGateway::class, fn ($app) => $app->make(PowerTranzGateway::class));

        $status = app(GatewayStatus::class);

        $this->assertFalse(
            $status->isFake(),
            'The banner still reports the fake after the container was rebound, so it is '
                .'guessing from settings instead of asking what was handed out.'
        );

        $this->assertSame('PowerTranzGateway', $status->className());
    }

    /**
     * ⚠️ THE HOST, BECAUSE A WRONG HOST FAILS SILENTLY.
     *
     * Sandbox credentials pointed at the live host fails in a way that looks
     * like a credential problem and costs a day.
     */
    public function test_the_banner_shows_which_host_it_resolved_to(): void
    {
        $this->app->bind(PaymentGateway::class, fn ($app) => $app->make(PowerTranzGateway::class));

        $host = app(GatewayStatus::class)->host();

        $this->assertNotNull($host, 'The real gateway reports no host, so a misconfigured endpoint is invisible.');
        $this->assertStringContainsString('ptranz.com', $host);
        $this->assertContains($host, array_values(PowerTranzGateway::HOSTS));
    }

    /**
     * ⚠️ LIVE MUST NOT LOOK LIKE THE SAFE STATES.
     *
     * The stand-in and the sandbox are both brass, because a mistake in
     * either costs nothing. Live is the one state where a mistake costs
     * somebody real money, so it gets a different colour, different text
     * colour and a rule — legible at a glance without reading a word.
     */
    public function test_live_is_visually_distinct_from_the_safe_states(): void
    {
        $css = File::get(resource_path('css/app.css'));

        preg_match('/\.rv-gatewaybar\s*\{([^}]*)\}/', $css, $safe);
        preg_match('/\.rv-gatewaybar--live\s*\{([^}]*)\}/', $css, $live);

        $this->assertNotEmpty($safe, 'The gateway banner rule has gone.');
        $this->assertNotEmpty($live, 'There is no distinct live treatment, so live looks like sandbox.');

        $this->assertStringContainsString('var(--brass)', $safe[1]);
        $this->assertStringNotContainsString(
            'var(--brass)',
            $live[1],
            'Live is still brass, which is what the two harmless states use.'
        );
        $this->assertStringContainsString('var(--rose)', $live[1]);
    }

    /**
     * ⚠️ MEMBERS DO NOT SEE IT. Which gateway is bound is operational detail,
     * and the prop must not travel to somebody with no use for it.
     */
    public function test_the_banner_is_withheld_from_members(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('gateway', null));
    }

    /** And an administrator does see it. */
    public function test_an_administrator_sees_the_banner(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('gateway.className')->has('gateway.label'));
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). Every assertion above reads either a
     * resolved object or a stylesheet, so both readers must be shown finding
     * the thing they claim to.
     */
    public function test_the_readers_can_see_what_they_claim_to(): void
    {
        // The CSS reader finds a real difference, and no difference when there is none.
        preg_match('/\.a\s*\{([^}]*)\}/', '.a { background: var(--brass); }', $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString('var(--brass)', $m[1]);

        preg_match('/\.b\s*\{([^}]*)\}/', '.b { background: var(--rose); }', $n);
        $this->assertStringNotContainsString('var(--brass)', $n[1]);

        // And the class reader distinguishes the two gateways.
        $this->app->bind(PaymentGateway::class, fn () => new FakeGateway);
        $fake = app(GatewayStatus::class)->className();

        $this->app->bind(PaymentGateway::class, fn ($app) => $app->make(PowerTranzGateway::class));
        $real = app(GatewayStatus::class)->className();

        $this->assertNotSame(
            $fake,
            $real,
            'The reader returns the same name for both gateways, so the banner cannot be '
                .'telling anybody which one is bound.'
        );
    }
}
