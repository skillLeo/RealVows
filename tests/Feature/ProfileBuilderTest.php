<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileBuilderTest extends TestCase
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
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('member');
        Profile::create(['user_id' => $user->id]);

        return $user;
    }

    public function test_autosave_persists_a_partial_step_and_survives_a_refresh(): void
    {
        $user = $this->member();

        // Deliberately half-finished — this is the normal state while
        // somebody is still typing, and it must not be rejected.
        $this->actingAs($user)
            ->putJson('/profile/personal', [
                'first_name' => 'Ayesha',
                'city' => 'Kingston',
            ])
            ->assertOk()
            ->assertJsonStructure(['saved_at', 'completeness', 'missing', 'submittable']);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'first_name' => 'Ayesha',
            'city' => 'Kingston',
        ]);

        // "Survives a refresh" means the server has it — nothing was held
        // only in the browser. A fresh request must return it.
        $this->actingAs($user)
            ->get('/profile/personal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('profile.personal.first_name', 'Ayesha')
                ->where('profile.personal.city', 'Kingston')
            );
    }

    public function test_the_18_gate_is_enforced_on_the_server_not_the_checkbox(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->putJson('/profile/personal', [
                'date_of_birth' => now()->subYears(17)->subMonths(11)->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.date_of_birth.0', 'RealVows is for members aged 18 and over.');

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'date_of_birth' => null,
        ]);
    }

    public function test_an_adult_date_of_birth_is_accepted(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->putJson('/profile/personal', [
                'date_of_birth' => now()->subYears(29)->toDateString(),
            ])
            ->assertOk();

        $this->assertNotNull($user->profile->fresh()->date_of_birth);
    }

    public function test_the_internal_derivation_runs_but_never_reaches_the_browser(): void
    {
        $user = $this->member();

        $this->actingAs($user)->putJson('/profile/personal', [
            'date_of_birth' => '1994-07-25',
        ])->assertOk();

        $profile = $user->profile->fresh();

        // It ran server-side...
        $this->assertNotNull($profile->sign);
        $this->assertNotNull($profile->element);

        // ...and neither column appears in anything serialised to the client.
        $serialised = json_encode($profile->toArray());
        $this->assertStringNotContainsString($profile->sign, $serialised);
        $this->assertStringNotContainsString('"sign"', $serialised);
        $this->assertStringNotContainsString('"element"', $serialised);

        $this->actingAs($user)
            ->get('/profile/personal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('profile.personal.sign')
                ->missing('profile.personal.element')
            );
    }

    public function test_an_unknown_enum_is_rejected(): void
    {
        $this->actingAs($this->member())
            ->putJson('/profile/personal', ['gender' => 'not-a-real-option'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('gender');
    }

    public function test_an_incomplete_profile_cannot_be_submitted_and_is_told_what_is_missing(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post('/profile/submit')->assertRedirect();

        $this->assertNull($user->profile->fresh()->submitted_at);
        $this->assertStringContainsString('Still needed', session('error'));
    }

    public function test_a_photo_with_a_faked_extension_is_rejected(): void
    {
        Storage::fake('local');
        $user = $this->member();

        // A text file wearing a .jpg name — the oldest upload attack there is.
        // Laravel's `image` rule trusts the guessed mime type, which follows
        // the extension, so this passes validation. The content check inside
        // the storage service is what actually stops it, and that is the
        // whole reason the check is there and not only in the rules.
        $fake = UploadedFile::fake()->createWithContent('portrait.jpg', '<?php echo "not a photo";');

        $this->actingAs($user)
            ->postJson('/photos', ['photo' => $fake, 'slot' => 'passport'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'That file does not look like a photo. JPEG, PNG or WebP, please.');

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_a_genuine_photo_uploads_and_moves_completeness(): void
    {
        Storage::fake('local');
        $user = $this->member();

        $this->actingAs($user)
            ->postJson('/photos', [
                'photo' => UploadedFile::fake()->image('portrait.jpg', 2000, 2500),
                'slot' => 'passport',
            ])
            ->assertOk()
            ->assertJsonStructure(['photo' => ['id', 'slot', 'url'], 'completeness']);

        $photo = $user->profile->photos()->first();

        $this->assertNotNull($photo);
        $this->assertSame('passport', $photo->slot);

        // Resized down the longest edge, and a WebP alongside the JPEG.
        $this->assertLessThanOrEqual(1600, $photo->width);
        $this->assertLessThanOrEqual(1600, $photo->height);
        Storage::disk('local')->assertExists($photo->path);
        Storage::disk('local')->assertExists($photo->webp_path);
        Storage::disk('local')->assertExists($photo->thumbnail_path);
    }

    public function test_a_photo_is_not_reachable_without_a_session(): void
    {
        Storage::fake('local');
        $user = $this->member();

        $this->actingAs($user)->postJson('/photos', [
            'photo' => UploadedFile::fake()->image('portrait.jpg'),
            'slot' => 'passport',
        ])->assertOk();

        $photo = $user->profile->photos()->first();
        $signedUrl = \URL::temporarySignedRoute('photos.show', now()->addHour(), ['photo' => $photo->id]);

        // Even holding a valid signed URL, a logged-out visitor gets nothing.
        auth()->logout();
        $this->get($signedUrl)->assertRedirect('/login');
    }

    public function test_one_member_cannot_read_another_members_photo(): void
    {
        Storage::fake('local');

        $owner = $this->member();
        $this->actingAs($owner)->postJson('/photos', [
            'photo' => UploadedFile::fake()->image('portrait.jpg'),
            'slot' => 'passport',
        ])->assertOk();

        $photo = $owner->profile->photos()->first();
        $signedUrl = \URL::temporarySignedRoute('photos.show', now()->addHour(), ['photo' => $photo->id]);

        // A correctly signed URL forwarded to somebody else is still useless,
        // because the ownership check is independent of the signature.
        $this->actingAs($this->member())->get($signedUrl)->assertNotFound();
    }

    public function test_the_verification_code_exists_by_the_time_the_member_reaches_the_photos_step(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->get('/profile/photos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('verification.status', 'pending'));

        $code = $user->profile->fresh()->verification->code;

        $this->assertNotNull($code);
        $this->assertStringStartsWith('RV-', $code);
        // No I, O, 0 or 1 — a reviewer must never have to guess a character.
        $this->assertDoesNotMatchRegularExpression('/[IO01]/', substr($code, 3));
    }
}
