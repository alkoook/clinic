<?php

use App\Enums\AppointmentKind;
use App\Enums\ClinicNotificationType;
use App\Enums\ParticipantPaymentStatus;
use App\Enums\PatientSource;
use App\Exceptions\FinancialException;
use App\Livewire\Notifications\Bell;
use App\Livewire\Notifications\Index;
use App\Models\Appointment;
use App\Models\Cashbox;
use App\Models\FinancialAgreement;
use App\Models\FinancialWeek;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\PractitionerAvailability;
use App\Models\Role;
use App\Models\Room;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\TreatmentType;
use App\Models\User;
use App\Notifications\ClinicEventNotification;
use App\Services\AppointmentService;
use App\Services\ClinicNotificationService;
use App\Services\PaymentService;
use App\Services\RefundService;
use App\Services\WeeklyClosingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function notificationUser(string $roleName, string ...$permissions): User
{
    $role = Role::query()->firstOrCreate(
        ['name' => $roleName],
        ['label' => $roleName],
    );

    foreach ($permissions as $permissionName) {
        $permission = Permission::query()->firstOrCreate(
            ['name' => $permissionName],
            ['label' => $permissionName, 'group' => 'tests'],
        );
        $role->permissions()->syncWithoutDetaching($permission);
    }

    return User::factory()->for($role)->create();
}

/** @return array{appointment: Appointment, admin: User, secretary: User, practitioner: Practitioner, other_practitioner: Practitioner} */
function createNotifiedAppointment(): array
{
    $admin = notificationUser('super-admin');
    $secretary = notificationUser('secretary', 'appointments.manage');
    $practitioner = Practitioner::factory()->create();
    $otherPractitioner = Practitioner::factory()->create();
    $room = Room::factory()->create();
    $treatmentType = TreatmentType::factory()->create();
    $patient = Patient::factory()->create(['current_practitioner_id' => $practitioner->id]);
    PractitionerAvailability::factory()->create([
        'practitioner_id' => $practitioner->id,
        'day_of_week' => 6,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'effective_from' => null,
    ]);

    $appointment = app(AppointmentService::class)->book([
        'practitioner_id' => $practitioner->id,
        'room_id' => $room->id,
        'treatment_type_id' => $treatmentType->id,
        'starts_at' => '2026-09-12 10:00:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$patient->id],
    ], $secretary);

    return compact('appointment', 'admin', 'secretary', 'practitioner', 'otherPractitioner');
}

it('creates a persisted in-app notification when an appointment is booked', function () {
    $scenario = createNotifiedAppointment();
    $notification = $scenario['admin']->notifications()->sole();

    expect($notification->type)->toBe(ClinicNotificationType::AppointmentCreated->value)
        ->and($notification->read_at)->toBeNull()
        ->and($notification->data['metadata']['appointment_id'])->toBe($scenario['appointment']->id);
});

it('sends an appointment notification only to the involved practitioner', function () {
    $scenario = createNotifiedAppointment();

    expect($scenario['practitioner']->user->notifications()->count())->toBe(1)
        ->and($scenario['otherPractitioner']->user->notifications()->count())->toBe(0);
});

it('sends operational appointment notifications to the secretary', function () {
    $scenario = createNotifiedAppointment();

    expect($scenario['secretary']->notifications()->where('type', ClinicNotificationType::AppointmentCreated->value)->count())
        ->toBe(1);
});

it('records reschedule and cancellation as distinct notification event types', function () {
    $scenario = createNotifiedAppointment();
    $service = app(AppointmentService::class);
    $rescheduled = $service->reschedule($scenario['appointment'], [
        'starts_at' => '2026-09-12 12:00:00',
    ], $scenario['secretary']);
    $service->cancel($rescheduled, 'اعتذار المريض', $scenario['secretary']);

    expect($scenario['admin']->notifications()->where('type', ClinicNotificationType::AppointmentRescheduled->value)->count())->toBe(1)
        ->and($scenario['admin']->notifications()->where('type', ClinicNotificationType::AppointmentCancelled->value)->count())->toBe(1);
});

it('does not send administrative financial details to secretaries', function () {
    $admin = notificationUser('super-admin');
    $secretary = notificationUser('secretary');
    $practitioner = Practitioner::factory()->create();

    app(ClinicNotificationService::class)->financial(
        'payment:44:received',
        ClinicNotificationType::PaymentReceived,
        'دفعة إدارية',
        'ربح العيادة: معلومات سرية',
        practitioner: $practitioner,
        practitionerTitle: 'دفعة تخصك',
        practitionerBody: 'تم تسجيل دفعة تخص جلستك',
    );

    expect($admin->notifications()->count())->toBe(1)
        ->and($secretary->notifications()->count())->toBe(0)
        ->and($practitioner->user->notifications()->sole()->data['body'])->not->toContain('ربح العيادة');
});

it('marks one notification as read', function () {
    $user = notificationUser('secretary');
    $user->notify(new ClinicEventNotification(ClinicNotificationType::AppointmentCreated, 'موعد', 'تفاصيل'));
    $notification = $user->notifications()->sole();

    Livewire::actingAs($user)->test(Bell::class)->call('markAsRead', $notification->id);

    expect($notification->refresh()->read_at)->not->toBeNull();
});

it('marks all of the current user notifications as read', function () {
    $user = notificationUser('secretary');
    $user->notify(new ClinicEventNotification(ClinicNotificationType::AppointmentCreated, 'موعد 1', 'تفاصيل'));
    $user->notify(new ClinicEventNotification(ClinicNotificationType::AppointmentCancelled, 'موعد 2', 'تفاصيل'));

    Livewire::actingAs($user)->test(Index::class)->call('markAllAsRead');

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('cannot mark another user notification as read', function () {
    $user = notificationUser('secretary');
    $other = notificationUser('practitioner');
    $other->notify(new ClinicEventNotification(ClinicNotificationType::AppointmentCreated, 'موعد', 'تفاصيل'));
    $notification = $other->notifications()->sole();

    Livewire::actingAs($user)
        ->test(Bell::class)
        ->call('markAsRead', $notification->id)
        ->assertForbidden();

    expect($notification->refresh()->read_at)->toBeNull();
});

it('shows only the authenticated user notifications page with filters', function () {
    $user = notificationUser('secretary');
    $other = notificationUser('practitioner');
    $user->notify(new ClinicEventNotification(ClinicNotificationType::AppointmentCreated, 'إشعاري الخاص', 'تفاصيل'));
    $other->notify(new ClinicEventNotification(ClinicNotificationType::AppointmentCreated, 'إشعار مستخدم آخر', 'تفاصيل'));

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('status', 'unread')
        ->assertSee('إشعاري الخاص')
        ->assertDontSee('إشعار مستخدم آخر');
});

it('records payment notifications only after a successful payment', function () {
    $admin = notificationUser('super-admin', 'payments.create');
    Cashbox::factory()->main()->create();
    Cashbox::factory()->side()->create();
    $practitioner = Practitioner::factory()->create();
    $treatmentType = TreatmentType::factory()->create();
    $appointment = Appointment::factory()->for($practitioner)->for($treatmentType)->create();
    $session = TreatmentSession::factory()->for($appointment)->create();
    $patient = Patient::factory()->for($practitioner, 'currentPractitioner')->create(['source' => PatientSource::Clinic]);
    $participant = TreatmentSessionParticipant::factory()->for($session)->for($patient)->create([
        'original_price' => 100_000,
        'discount_amount' => 0,
        'final_amount' => 100_000,
        'payment_status' => ParticipantPaymentStatus::Unpaid,
    ]);
    FinancialAgreement::factory()->for($practitioner)->create([
        'practitioner_percentage' => 60,
        'effective_from' => '2020-01-01',
        'effective_to' => null,
        'is_active' => true,
    ]);

    $service = app(PaymentService::class);
    $service->pay($participant, 100_000, 0, $admin, (string) Str::uuid());
    $failedRetry = fn () => $service->pay($participant, 100_000, 0, $admin, (string) Str::uuid());

    expect($failedRetry)->toThrow(FinancialException::class)
        ->and($admin->notifications()->where('type', ClinicNotificationType::PaymentReceived->value)->count())->toBe(1);
});

it('includes the correct amount in refund notification metadata', function () {
    $admin = notificationUser('super-admin', 'payments.create', 'refunds.create');
    Cashbox::factory()->main()->create();
    Cashbox::factory()->side()->create();
    $practitioner = Practitioner::factory()->create();
    $treatmentType = TreatmentType::factory()->create();
    $appointment = Appointment::factory()->for($practitioner)->for($treatmentType)->create();
    $session = TreatmentSession::factory()->for($appointment)->create();
    $patient = Patient::factory()->for($practitioner, 'currentPractitioner')->create(['source' => PatientSource::Clinic]);
    $participant = TreatmentSessionParticipant::factory()->for($session)->for($patient)->create([
        'original_price' => 100_000,
        'discount_amount' => 0,
        'final_amount' => 100_000,
        'payment_status' => ParticipantPaymentStatus::Unpaid,
    ]);
    FinancialAgreement::factory()->for($practitioner)->create([
        'practitioner_percentage' => 60,
        'effective_from' => '2020-01-01',
        'effective_to' => null,
        'is_active' => true,
    ]);
    $payment = app(PaymentService::class)->pay($participant, 100_000, 0, $admin, (string) Str::uuid());

    app(RefundService::class)->refund($payment, 25_000, 'تصحيح', $admin, (string) Str::uuid());
    $notification = $admin->notifications()->where('type', ClinicNotificationType::PaymentRefunded->value)->sole();

    expect($notification->data['metadata']['amount'])->toBe(25_000)
        ->and($notification->data['body'])->toContain('25,000');
});

it('sends weekly closing notification once on an idempotent repeated request', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-11 12:00:00'));
    $admin = notificationUser('super-admin', 'cashboxes.manage');
    Cashbox::factory()->main()->create();
    Cashbox::factory()->side()->create();
    $week = FinancialWeek::factory()->create([
        'starts_on' => '2026-09-05',
        'ends_on' => '2026-09-10',
        'status' => 'open',
    ]);
    $key = (string) Str::uuid();
    $service = app(WeeklyClosingService::class);

    $first = $service->close($week, $admin, $key);
    $second = $service->close($week, $admin, $key);

    expect($second->is($first))->toBeTrue()
        ->and($admin->notifications()->where('type', ClinicNotificationType::FinancialWeekClosed->value)->count())->toBe(1);
});
