<?php

use App\Models\Appointment;
use App\Models\Expense;
use App\Models\FinancialWeek;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Practitioner;
use App\Models\PractitionerSettlement;
use App\Models\Refund;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Models\WeeklyClosing;
use App\Services\AvailabilityService;
use App\Services\FinancialAgreementService;
use App\Services\ReportAnalyticsService;
use Database\Seeders\DemoClinicSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

it('creates a realistic linked demo clinic without becoming a test prerequisite', function () {
    $this->seed(DemoClinicSeeder::class);

    expect(User::query()->whereIn('username', ['admin', 'secretary', 'doctor1', 'doctor2', 'therapist1'])->count())->toBe(5)
        ->and(Hash::check('Clinic@123456', User::query()->where('username', 'admin')->sole()->password))->toBeTrue()
        ->and(Practitioner::query()->count())->toBe(6)
        ->and(Patient::query()->count())->toBe(80)
        ->and(Appointment::query()->count())->toBeGreaterThanOrEqual(100)
        ->and(TreatmentSession::query()->count())->toBeGreaterThanOrEqual(10)
        ->and(Payment::query()->count())->toBeGreaterThanOrEqual(10)
        ->and(Expense::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(Refund::query()->count())->toBe(2)
        ->and(WeeklyClosing::query()->count())->toBe(2)
        ->and(FinancialWeek::query()->where('status', 'open')->exists())->toBeTrue()
        ->and(PractitionerSettlement::query()->where('status', 'partially_paid')->exists())->toBeTrue();

    $appointments = Appointment::query()->whereNotIn('status', ['cancelled', 'rescheduled'])->get();
    foreach ($appointments as $appointment) {
        expect(app(AvailabilityService::class)->isAvailable($appointment->practitioner_id, $appointment->starts_at, $appointment->ends_at))->toBeTrue();
        $overlap = $appointments->contains(fn (Appointment $other): bool => $other->id !== $appointment->id
            && ($other->room_id === $appointment->room_id || $other->practitioner_id === $appointment->practitioner_id)
            && $other->starts_at->lt($appointment->ends_at) && $other->ends_at->gt($appointment->starts_at));
        expect($overlap)->toBeFalse();
    }
    foreach (Payment::query()->with('treatmentSessionParticipant.treatmentSession')->get() as $payment) {
        $participant = $payment->treatmentSessionParticipant;
        $session = $participant->treatmentSession;
        expect(app(FinancialAgreementService::class)->findEffective(
            $session->practitioner_id, $session->started_at,
        ))->not->toBeNull();
    }
});

it('feeds the advanced room and treatment reports from demo data', function () {
    $this->seed(DemoClinicSeeder::class);
    $analytics = app(ReportAnalyticsService::class);
    $from = now()->subMonth()->toDateString();
    $to = now()->toDateString();
    $rooms = $analytics->rooms($from, $to)->get();
    $treatments = $analytics->treatments($from, $to)->get();

    expect($rooms)->toHaveCount(3)
        ->and((int) $rooms->sum('appointments_count'))->toBeGreaterThan(0)
        ->and((int) $rooms->sum('room_revenue'))->toBeGreaterThan(0)
        ->and((int) $rooms->sum('completed_sessions_count'))->toBeGreaterThan(0)
        ->and($treatments)->not->toBeEmpty()
        ->and((int) $treatments->sum('patients_count'))->toBeGreaterThan(0)
        ->and((int) $treatments->sum('treatment_revenue'))->toBeGreaterThan(0)
        ->and($treatments->pluck('practitioner_distribution')->filter())->not->toBeEmpty();
});
