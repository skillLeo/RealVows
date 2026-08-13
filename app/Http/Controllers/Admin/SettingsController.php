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
        'packages' => 'Packages',
        'payments' => 'Payments',
        'whatsapp' => 'WhatsApp',
    ];

    private const TESTABLE = ['mail', 'storage'];

    /**
     * ⚠️ THE ONE LIST, READ BY BOTH THIS PAGE AND THE ADMIN SIDEBAR.
     *
     * Navigation::admin() renders a link per group. Writing those out a second
     * time there would mean two lists of the same thing, and two lists of the
     * same thing drift — the drift showing up as a sidebar item that 404s,
     * which is the exact defect Navigation exists to prevent.
     *
     * @return array<string, string>
     */
    public static function groups(): array
    {
        return self::GROUPS;
    }

    public function __construct(private readonly SettingsService $settings) {}

    public function index(Request $request, string $group = 'general'): Response
    {
        abort_unless(array_key_exists($group, self::GROUPS), 404);

        // How many settings in each group still have no value. The tab badge
        // then means something — it tells the client where his attention is
        /*
         * ⚠️ THE GROUP LIST AND ITS COUNTS MOVED TO THE SIDEBAR.
         *
         * Both used to be sent from here to draw a tab strip on this page.
         * The strip is gone — nine tabs wrapped onto a second row and stopped
         * being navigation — and Navigation::admin() renders the same eight
         * groups with the same counts down the left instead.
         *
         * Sending them anyway would leave this page shipping a payload nothing
         * reads, which is how a prop outlives the thing it was for and starts
         * looking like a requirement to the next person.
         */
        return Inertia::render('Admin/Settings', [
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

            /*
             * ⚠️ THE PUBLIC PAGES READ A PROJECTION, NOT THIS TABLE.
             *
             * Switching the free period off here and leaving the projection
             * alone would mean the marketing pages went on hiding the Pricing
             * link after payments were live. Rewritten on every save — cheap,
             * and it makes the change visible immediately rather than at the
             * next scheduler tick.
             *
             * A failed write is not fatal and is not swallowed either: the
             * scheduler rewrites it every minute, and PublicState logs.
             */
            \App\Support\PublicState::write(app(\App\Support\FreePeriod::class)->isActive());
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
