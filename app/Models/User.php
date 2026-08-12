<?php

namespace App\Models;

use App\Notifications\ResetPasswordLink;
use App\Notifications\VerifyEmailAddress;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone_country_code', 'phone_number'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'is_demo' => 'boolean',
            // Meta's 24-hour customer service window. See ConversationWindow.
            'whatsapp_last_inbound_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function creditEntries(): HasMany
    {
        return $this->hasMany(CreditLedgerEntry::class);
    }

    /**
     * Credit balance is DERIVED, never stored.
     *
     * Summing the append-only ledger means there is no counter that can drift
     * out of step with the rows justifying it, and a double-credit becomes
     * impossible rather than merely unlikely.
     *
     * In Phase 1 this always returns 0 — nothing grants credits until
     * payments land in Phase 2 — but the shape is right from day one, so no
     * screen has to change when it does.
     */
    public function creditBalance(): int
    {
        return (int) $this->creditEntries()->sum('delta');
    }

    public function phoneIsVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function fullPhone(): ?string
    {
        if (! $this->phone_country_code || ! $this->phone_number) {
            return null;
        }

        return $this->phone_country_code.' '.$this->phone_number;
    }

    /**
     * ⚠️ THE STAFF ROLES, AND THE REVIEWER ROLES ARE STAFF.
     *
     * `reviewer_female` and `reviewer_male` were added for gender-separated
     * verification review. They hold `verifications.view` and
     * `verifications.review`, so the permission check further down the
     * middleware would have let them through — but this list gate runs first
     * and rejected them, giving a 404 that looked exactly like the gender
     * scope refusing an out-of-scope record.
     *
     * ⚠️ THAT IS WHY ReviewerScopeTest CARRIES A POSITIVE CONTROL. Every scope
     * assertion is "expect 404", and a route that refuses everybody returns
     * 404 too. Without a test asserting the RIGHT reviewer gets through, the
     * whole separation would have reported itself working while nobody could
     * review anything at all.
     *
     * `guardian` is deliberately NOT here. A guardian has an account and a
     * dashboard of their own; they are not staff and must never reach /admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole([
            'admin',
            'super_admin',
            \App\Support\ReviewerScope::FEMALE,
            \App\Support\ReviewerScope::MALE,
        ]);
    }

    /** Branded in the RealVows frame rather than Laravel's generic default. */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailAddress());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }
}
