<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class RecordAuditLog
{
    /**
     * Write one immutable line of history.
     *
     * @param  array<string, mixed>  $meta
     */
    public function handle(
        string $action,
        ?Model $subject = null,
        array $meta = [],
        ?User $actor = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => ($actor ?? Request::user())?->id,
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
        ]);
    }

    /**
     * Fingerprint a secret for the audit trail.
     *
     * The log records that a credential changed and that the new value
     * differs from the old one — never what either value was. An audit trail
     * that leaked the credentials it audits would be worse than none.
     */
    public function fingerprint(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr(hash('sha256', $value), 0, 16);
    }
}
