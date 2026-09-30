<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->json('legacy_context')->nullable();
            $table->decimal('practitioner_percentage', 5, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(
                ['practitioner_id', 'is_active', 'effective_from', 'effective_to'],
                'practitioner_agreement_period_index',
            );
        });

        Schema::table('treatment_session_participants', function (Blueprint $table) {
            $table->foreignId('financial_agreement_id')
                ->nullable()
                ->after('patient_source')
                ->constrained('financial_agreements')
                ->restrictOnDelete();
        });

        Schema::create('financial_weeks', function (Blueprint $table) {
            $table->id();
            $table->date('starts_on')->index();
            $table->date('ends_on');
            $table->timestamp('opened_at')->nullable()->index();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('open')->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cashboxes', function (Blueprint $table) {
            $table->id();
            $table->string('type', 24)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cashbox_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashbox_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_week_id')->constrained()->restrictOnDelete();
            $table->string('type', 40)->index();
            $table->string('direction', 8);
            $table->unsignedBigInteger('amount');
            $table->nullableMorphs('reference');
            $table->string('description')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['cashbox_id', 'occurred_at']);
            $table->index(['financial_week_id', 'type']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_session_participant_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('financial_week_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashbox_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method', 24)->default('cash');
            $table->string('status', 24)->default('paid');
            $table->dateTime('paid_at')->index();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('practitioner_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('treatment_session_participant_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_week_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('gross_amount');
            $table->decimal('practitioner_percentage', 5, 2);
            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->default('accrued');
            $table->dateTime('earned_at')->index();
            $table->timestamps();
            $table->index(['practitioner_id', 'financial_week_id', 'status'], 'practitioner_week_earning_index');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_week_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashbox_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->text('reason')->nullable();
            $table->string('status', 24)->default('completed');
            $table->boolean('after_closed_week')->default(false);
            $table->dateTime('refunded_at')->index();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('treatment_session_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('financial_agreement_id');
        });
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('practitioner_earnings');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cashbox_transactions');
        Schema::dropIfExists('cashboxes');
        Schema::dropIfExists('financial_weeks');
        Schema::dropIfExists('financial_agreements');
    }
};
