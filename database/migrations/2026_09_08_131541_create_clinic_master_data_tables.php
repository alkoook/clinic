<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_identifier')->nullable()->index();
            $table->timestamp('login_at');
            $table->timestamp('logout_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_name')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'login_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->index();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 32)->default('string');
            $table->timestamps();
        });

        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('treatment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialty_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_group')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('practitioners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('name')->index();
            $table->string('phone')->nullable()->index();
            $table->string('telegram_chat_id')->nullable();
            $table->json('contact_data')->nullable();
            $table->text('administrative_notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('practitioner_specialty', function (Blueprint $table) {
            $table->foreignId('practitioner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained()->cascadeOnDelete();
            $table->primary(['practitioner_id', 'specialty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practitioner_specialty');
        Schema::dropIfExists('practitioners');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('treatment_types');
        Schema::dropIfExists('specialties');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('login_activities');
    }
};
