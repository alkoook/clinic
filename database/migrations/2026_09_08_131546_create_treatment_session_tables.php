<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('treatment_type_id')->constrained()->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('status', 24)->default('pending_payment');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('practitioner_name');
            $table->string('room_name');
            $table->string('treatment_type_name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['practitioner_id', 'started_at', 'status']);
            $table->index(['status', 'started_at']);
        });

        Schema::create('treatment_session_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_participant_id')
                ->nullable()
                ->unique()
                ->constrained(indexName: 'session_participant_appointment_fk')
                ->nullOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_practitioner_id')->nullable()->constrained('practitioners')->nullOnDelete();
            $table->string('patient_name');
            $table->string('patient_source', 32);
            $table->unsignedBigInteger('original_price')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('final_amount')->default(0);
            $table->decimal('practitioner_percentage', 5, 2)->nullable();
            $table->unsignedBigInteger('practitioner_share')->nullable();
            $table->unsignedBigInteger('clinic_share')->nullable();
            $table->string('payment_status', 24)->default('unpaid');
            $table->foreignId('discount_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['treatment_session_id', 'patient_id'], 'session_patient_unique');
            $table->index(['patient_id', 'payment_status']);
        });

        Schema::create('patient_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('stored_name');
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['patient_id', 'uploaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_attachments');
        Schema::dropIfExists('treatment_session_participants');
        Schema::dropIfExists('treatment_sessions');
    }
};
