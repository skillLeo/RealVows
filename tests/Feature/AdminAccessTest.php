<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_a_member_hitting_admin_gets_404_and_never_403(): void
    {
        $response = $this->actingAs($this->member())->get('/admin/settings');

        // 403 would confirm the area exists. 404 tells them nothing.
        $response->assertNotFound();
        $this->assertNotSame(403, $response->getStatusCode());
    }

    public function test_a_reviewer_admin_cannot_reach_settings(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('admin');

        // A person hired to check photographs has no business holding the
        // payment gateway keys.
        $this->actingAs($reviewer)->get('/admin/settings')->assertNotFound();
    }

    public function test_a_super_admin_can_open_settings(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin/settings')
            ->assertOk();
    }

    public function test_a_secret_is_encrypted_at_rest_and_only_ever_leaves_masked(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('mail', 'password', 'super-secret-value');

        $row = Setting::where('group', 'mail')->where('key', 'password')->first();

        $this->assertNotSame('super-secret-value', $row->value, 'Stored value must be ciphertext.');
        $this->assertSame('super-secret-value', $row->plainValue());
        $this->assertStringNotContainsString('super-secret', $row->maskedValue());
        $this->assertStringEndsWith('alue', $row->maskedValue());
    }

    public function test_resubmitting_an_unchanged_mask_does_not_destroy_the_real_secret(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('mail', 'password', 'super-secret-value');

        $row = Setting::where('group', 'mail')->where('key', 'password')->first();

        // This is exactly what the settings form posts back for a field the
        // client did not touch.
        $this->actingAs($this->superAdmin())->post('/admin/settings', [
            'group' => 'mail',
            'values' => ['password' => $row->maskedValue()],
        ]);

        $this->assertSame('super-secret-value', $row->fresh()->plainValue());
    }

    public function test_changing_a_secret_is_audited_without_recording_the_value(): void
    {
        $this->actingAs($this->superAdmin())->post('/admin/settings', [
            'group' => 'mail',
            'values' => ['password' => 'brand-new-secret'],
        ]);

        $log = \App\Models\AuditLog::where('action', 'settings.updated')->latest('id')->first();

        $this->assertNotNull($log);

        $encoded = json_encode($log->meta);
        $this->assertStringNotContainsString('brand-new-secret', $encoded);
        $this->assertNotEmpty($log->meta['changes']['password']['to']);
    }
}
