<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\PatientAttachmentController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ReportExportDownloadController;
use App\Livewire\Appointments\Calendar as AppointmentCalendar;
use App\Livewire\Appointments\Form as AppointmentForm;
use App\Livewire\Appointments\Index as AppointmentIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Availability\Index;
use App\Livewire\Availability\Manage as AvailabilityManage;
use App\Livewire\Dashboard;
use App\Livewire\Finance\Agreements;
use App\Livewire\Finance\AuditLog;
use App\Livewire\Finance\Cashboxes;
use App\Livewire\Finance\Expenses;
use App\Livewire\Finance\Settlements;
use App\Livewire\Finance\WeeklyClosing;
use App\Livewire\MasterData\Rooms;
use App\Livewire\MasterData\Specialties;
use App\Livewire\MasterData\TreatmentTypes;
use App\Livewire\Notifications\Index as NotificationIndex;
use App\Livewire\Patients\Form as PatientForm;
use App\Livewire\Patients\Index as PatientIndex;
use App\Livewire\Patients\Show as PatientShow;
use App\Livewire\Practitioners\Form as PractitionerForm;
use App\Livewire\Practitioners\Index as PractitionerIndex;
use App\Livewire\Practitioners\Show as PractitionerShow;
use App\Livewire\Reports\Index as ReportIndex;
use App\Livewire\Sessions\Index as SessionIndex;
use App\Livewire\Sessions\Show as SessionShow;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Livewire\Users\Index as UserIndex;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/notifications', NotificationIndex::class)->name('notifications.index');

    Route::prefix('patients')->name('patients.')->group(function (): void {
        Route::get('/', PatientIndex::class)->name('index');
        Route::get('/create', PatientForm::class)->name('create');
        Route::get('/{patient}/edit', PatientForm::class)->name('edit');
        Route::get('/{patient}', PatientShow::class)->name('show');
        Route::get('/{patient}/attachments/{patientAttachment}', [PatientAttachmentController::class, 'download'])
            ->name('attachments.download');
        Route::get('/{patient}/attachments/{patientAttachment}/preview', [PatientAttachmentController::class, 'preview'])
            ->name('attachments.preview');
    });

    Route::prefix('appointments')->name('appointments.')->group(function (): void {
        Route::get('/', AppointmentIndex::class)->name('index');
        Route::get('/calendar', AppointmentCalendar::class)->name('calendar');
        Route::get('/create', AppointmentForm::class)->name('create');
        Route::get('/{appointment}/reschedule', AppointmentForm::class)->name('reschedule');
    });

    Route::get('/availability', function () {
        $user = request()->user();

        if ($user->practitioner && $user->can('practitioner.availability.manage_own')) {
            return redirect()->route('availability.manage', $user->practitioner);
        }

        abort_unless($user->can('practitioners.manage') || $user->can('appointments.manage'), 403);

        return redirect()->route('availability.index');
    })->name('availability.mine');
    Route::get('/availability/practitioners', Index::class)->name('availability.index');
    Route::get('/practitioners/{practitioner}/availability', AvailabilityManage::class)->name('availability.manage');

    Route::prefix('sessions')->name('sessions.')->group(function (): void {
        Route::get('/', SessionIndex::class)->name('index');
        Route::get('/{treatmentSession}', SessionShow::class)->name('show');
    });

    Route::prefix('practitioners')->name('practitioners.')->group(function (): void {
        Route::get('/', PractitionerIndex::class)->name('index');
        Route::get('/my-profile', function () {
            $practitioner = request()->user()->practitioner;
            abort_unless($practitioner, 404);

            return redirect()->route('practitioners.show', $practitioner);
        })->name('mine');
        Route::get('/create', PractitionerForm::class)->name('create');
        Route::get('/{practitioner}/edit', PractitionerForm::class)->name('edit');
        Route::get('/{practitioner}', PractitionerShow::class)->name('show');
    });

    Route::prefix('master-data')->name('master-data.')->group(function (): void {
        Route::get('/specialties', Specialties::class)->name('specialties');
        Route::get('/treatment-types', TreatmentTypes::class)->name('treatment-types');
        Route::get('/rooms', Rooms::class)->name('rooms');
    });

    Route::get('/users', UserIndex::class)->name('users.index');

    Route::prefix('finance')->name('finance.')->group(function (): void {
        Route::get('/agreements', Agreements::class)->name('agreements');
        Route::get('/expenses', Expenses::class)->name('expenses');
        Route::get('/cashboxes', Cashboxes::class)->name('cashboxes');
        Route::get('/weekly-closing', WeeklyClosing::class)->name('weekly-closing');
        Route::get('/settlements', Settlements::class)->name('settlements');
        Route::get('/audit-log', AuditLog::class)->name('audit-log');
    });

    Route::get('/reports', ReportIndex::class)->name('reports.index');
    Route::get('/reports/export', ReportExportController::class)->name('reports.export');
    Route::get('/reports/exports/{reportExport}/download', ReportExportDownloadController::class)->name('reports.exports.download');
    Route::get('/settings', SettingsIndex::class)->name('settings.index');
});
