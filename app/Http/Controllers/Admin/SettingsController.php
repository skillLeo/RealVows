<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAuditLog;
use App\Actions\Settings\TestSettingsConnection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Ordered so the groups the client actually touches sit first, and the
     * ones belonging to later phases sit last.
     */
    private const GROUPS = [
        'general' => 'General',
        'mail' => 'Email',
        'storage' => 'Photo storage',
        'verification' => 'Verification',
        'matching' => 'Matching',
        'payments' => 'Payments',
        'whatsapp' => 'WhatsApp',
    ];

    private const TESTABLE = ['mail', 'storage'];

    public function __construct(private readonly SettingsService $settings) {}

    public function index(Request $request, string $group = 'general'): Response
    {
        abort_unless(array_key_exists($group, self::GROUPS), 404);

        return Inertia::render('Admin/Settings', [
            'groups' => collect(self::GROUPS)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                ->values(),
            'activeGroup' => $group,
            'activeLabel' => self::GROUPS[$group],
            'fields' => $this->settings->groupForDisplay($group),
            'testable' => in_array($group, self::TESTABLE, true),
        ]);
    }

    public function update(UpdateSettingsRequest $request, RecordAuditLog $audit): RedirectResponse
    {
        $group = $request->validated('group');
        $changed = [];

        foreach ($request->validated('values') as $key => $value) {
            $setting = Setting::where('group', $group)->where('key', $key)->first();

            if (! $setting) {
                continue;
            }

            $before = $setting->plainValue();

            // set() refuses an unchanged mask, so a secret the client did not
            // touch survives the save untouched.
            if (! $this->settings->set($group, $key, $value)) {
                continue;
            }

            $changed[$key] = $setting->is_secret
                // Never the value. Only proof that it changed, and to what
                // fingerprint, so the trail is verifiable without being a leak.
                ? ['from' => $audit->fingerprint($before), 'to' => $audit->fingerprint($value)]
                : ['from' => $before, 'to' => $value];
        }

        if ($changed) {
            $audit->handle('settings.updated', null, ['group' => $group, 'changes' => $changed]);
        }

        return back()->with('success', $changed
            ? 'Saved.'
            : 'Nothing changed.');
    }

    public function test(Request $request, TestSettingsConnection $tester): RedirectResponse
    {
        $group = $request->string('group')->toString();

        abort_unless(in_array($group, self::TESTABLE, true), 404);

        $result = $tester->handle($group);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
