<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['role_id', 'username', 'name', 'email', 'password', 'is_active', 'last_login_at', 'theme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function practitioner(): HasOne
    {
        return $this->hasOne(Practitioner::class);
    }

    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class);
    }

    public function createdPatients(): HasMany
    {
        return $this->hasMany(Patient::class, 'created_by');
    }

    public function updatedPatients(): HasMany
    {
        return $this->hasMany(Patient::class, 'updated_by');
    }

    public function createdAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'created_by');
    }

    public function updatedAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'updated_by');
    }

    public function createdTreatmentSessions(): HasMany
    {
        return $this->hasMany(TreatmentSession::class, 'created_by');
    }

    public function approvedSessionParticipantDiscounts(): HasMany
    {
        return $this->hasMany(TreatmentSessionParticipant::class, 'discount_approved_by');
    }

    public function uploadedPatientAttachments(): HasMany
    {
        return $this->hasMany(PatientAttachment::class, 'uploaded_by');
    }

    public function createdFinancialAgreements(): HasMany
    {
        return $this->hasMany(FinancialAgreement::class, 'created_by');
    }

    public function createdCashboxTransactions(): HasMany
    {
        return $this->hasMany(CashboxTransaction::class, 'created_by');
    }

    public function receivedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    public function processedRefunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'refunded_by');
    }

    public function createdExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    public function createdCashboxTransfers(): HasMany
    {
        return $this->hasMany(CashboxTransfer::class, 'created_by');
    }

    public function createdFinancialAdjustments(): HasMany
    {
        return $this->hasMany(FinancialAdjustment::class, 'created_by');
    }

    public function closedWeeks(): HasMany
    {
        return $this->hasMany(WeeklyClosing::class, 'closed_by');
    }

    public function paidPractitionerSettlements(): HasMany
    {
        return $this->hasMany(PractitionerSettlementPayment::class, 'paid_by');
    }

    public function financialAuditLogs(): HasMany
    {
        return $this->hasMany(FinancialAuditLog::class);
    }

    public function reportExports(): HasMany
    {
        return $this->hasMany(ReportExport::class, 'requested_by');
    }

    public function hasRole(string $role): bool
    {
        return $this->role?->name === $role;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->relationLoaded('role')) {
            return $this->role?->hasPermission($permission) ?? false;
        }

        return $this->role()
            ->whereHas('permissions', fn (Builder $query): Builder => $query->where('name', $permission))
            ->exists();
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
