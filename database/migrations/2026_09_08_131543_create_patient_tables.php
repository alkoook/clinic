<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('medical_file_number');
            $table->string('full_name')->index();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('gender', 16)->nullable();
            $table->string('marital_status', 24)->nullable();
            $table->string('occupation')->nullable();
            $table->string('companion_name')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('incoming_hospital_name')->nullable()->index();
            $table->string('source', 32)->index();
            $table->foreignId('referring_practitioner_id')->nullable()->constrained('practitioners')->nullOnDelete();
            $table->foreignId('current_practitioner_id')->nullable()->constrained('practitioners')->nullOnDelete();
            $table->boolean('was_referred_out')->default(false);
            $table->string('outgoing_hospital_name')->nullable();
            $table->date('referred_out_at')->nullable();
            $table->text('referral_notes')->nullable();
            $table->text('administrative_notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['source', 'created_at']);
            $table->unique(['current_practitioner_id', 'medical_file_number'], 'patients_practitioner_file_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
