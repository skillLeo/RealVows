<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Concerns members have raised, as a queue with its own count.
 *
 * ⚠️ Nothing here notifies the reported member, at any status. Not when it
 * is opened, not when it is dismissed, not when it is actioned. A reporting
 * system that leaks back to its subject invites retaliation against the
 * person who spoke up.
 */
class ReportQueueController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: Report::STATUS_OPEN;

        $reports = Report::query()
            ->with(['reporter:id,first_name', 'subject:id,first_name,last_name'])
            ->where('status', $status)
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Report $r) => [
                'id' => $r->id,
                'reporter' => $r->reporter?->first_name,
                'subject' => trim(($r->subject?->first_name ?? '').' '.($r->subject?->last_name ?? '')),
                'subjectProfileId' => $r->subject_profile_id,
                'reason' => $r->reasonLabel(),
                'note' => $r->note,
                'raisedAt' => $r->created_at->format('j M Y, H:i'),
                'matchId' => $r->match_id,
            ]);

        return Inertia::render('Admin/Reports', [
            'reports' => $reports,
            'status' => $status,
            'counts' => [
                'open' => Report::where('status', 'open')->count(),
                'reviewed' => Report::where('status', 'reviewed')->count(),
                'actioned' => Report::where('status', 'actioned')->count(),
                'dismissed' => Report::where('status', 'dismissed')->count(),
            ],
        ]);
    }

    public function resolve(Request $request, Report $report, RecordAuditLog $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:reviewed,actioned,dismissed'],
            'reviewer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $report->update([
            'status' => $data['status'],
            'reviewer_note' => $data['reviewer_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $audit->handle('report.resolved', $report, [
            'status' => $data['status'],
            'subject_profile_id' => $report->subject_profile_id,
        ], $request->user());

        return back()->with('success', 'Report closed.');
    }
}
