<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAuditLog;
use App\Exceptions\InsufficientCredits;
use App\Http\Controllers\Controller;
use App\Models\CreditLedgerEntry;
use App\Models\User;
use App\Services\Credits\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manual credit adjustment. super_admin only.
 *
 * ⚠️ There is no balance to edit, by design. This writes an ordinary ledger
 * row with a reason and the staff member who made it, so a year later the
 * history still explains itself.
 */
class CreditAdminController extends Controller
{
    public function __construct(private readonly CreditService $credits) {}

    public function show(User $user): Response
    {
        $entries = CreditLedgerEntry::query()
            ->where('user_id', $user->id)
            ->with('user:id,email')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (CreditLedgerEntry $e) => [
                'id' => $e->id,
                'delta' => $e->delta,
                'balanceAfter' => $e->balance_after,
                'reason' => $e->reason,
                'note' => $e->note,
                'at' => $e->created_at?->format('j M Y, H:i'),
            ]);

        return Inertia::render('Admin/Credits', [
            'member' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->profile?->first_name,
            ],
            'balance' => $user->creditBalance(),
            'entries' => $entries,
        ]);
    }

    public function store(Request $request, User $user, RecordAuditLog $audit): RedirectResponse
    {
        $data = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0', 'min:-50', 'max:50'],
            'reason' => ['required', 'in:admin_adjustment,goodwill'],
            // Mandatory. The audit trail is the entire point of doing this
            // through a form rather than through the database.
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'note.required' => 'Say why. This is the record somebody reads in a year.',
            'delta.not_in' => 'An adjustment of zero would do nothing.',
        ]);

        try {
            $entry = $this->credits->adjust(
                $user,
                (int) $data['delta'],
                $data['reason'],
                $data['note'],
                $request->user(),
            );
        } catch (InsufficientCredits $e) {
            return back()->with('error', $e->getMessage());
        }

        $audit->handle('credits.adjusted', $entry, [
            'user_id' => $user->id,
            'delta' => $data['delta'],
            'reason' => $data['reason'],
        ], $request->user());

        return back()->with('success', 'Adjustment recorded.');
    }
}
