<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practitioner_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['practitioner_id', 'day_of_week', 'is_active'], 'practitioner_recurring_availability_index');
        });

        Schema::create('practitioner_availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 24);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['practitioner_id', 'date', 'type'], 'practitioner_exception_lookup_index');
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('treatment_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('kind', 24)->default('individual');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('status', 24)->default('scheduled');
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['practitioner_id', 'starts_at', 'ends_at', 'status'], 'practitioner_appointment_conflict_index');
            $table->index(['room_id', 'starts_at', 'ends_at', 'status'], 'room_appointment_conflict_index');
            $table->index(['status', 'starts_at']);
        });

        Schema::create('appointment_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('scheduled');
            $table->timestamps();
            $table->unique(['appointment_id', 'patient_id']);
            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_participants');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('practitioner_availability_exceptions');
        Schema::dropIfExists('practitioner_availabilities');
    }
};
