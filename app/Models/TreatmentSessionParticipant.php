<?php

namespace App\Models;

use App\Enums\ParticipantPaymentStatus;
use App\Enums\PatientSource;
use Database\Factories\TreatmentSessionParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'treatment_session_id',
    'appointment_participant_id',
    'patient_id',
    'source_practitioner_id',
    'patient_name',
    'patient_source',
    'financial_agreement_id',
    'original_price',
    'discount_amount',
    'final_amount',
    'practitioner_percentage',
    'practitioner_share',
    'clinic_share',
    'payment_status',
    'discount_approved_by',
])]
class TreatmentSessionParticipant extends Model
{
    /** @use HasFactory<TreatmentSessionParticipantFactory> */
    use HasFactory;

    public function treatmentSession(): BelongsTo
    {
        return $this->belongsTo(TreatmentSession::class);
    }

    public function appointmentParticipant(): BelongsTo
    {
        return $this->belongsTo(AppointmentParticipant::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function sourcePractitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'source_practitioner_id');
    }

    public function financialAgreement(): BelongsTo
    {
        return $this->belongsTo(FinancialAgreement::class)->withTrashed();
    }

    public function discountApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_approved_by');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function earning(): HasOne
    {
        return $this->hasOne(PractitionerEarning::class);
    }

    public function practitionerEarning(): HasOne
    {
        return $this->hasOne(PractitionerEarning::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patient_source' => PatientSource::class,
            'original_price' => 'integer',
            'discount_amount' => 'integer',
            'final_amount' => 'integer',
            'practitioner_percentage' => 'decimal:2',
            'practitioner_share' => 'integer',
            'clinic_share' => 'integer',
            'payment_status' => ParticipantPaymentStatus::class,
        ];
    }
}
