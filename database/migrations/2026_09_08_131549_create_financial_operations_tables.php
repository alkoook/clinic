<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashbox_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_week_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashbox_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->dateTime('occurred_at')->index();
            $table->text('description')->nullable();
            $table->boolean('affects_weekly_net')->default(true);
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['financial_week_id', 'affects_weekly_net']);
        });

        Schema::create('cashbox_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_cashbox_id')->constrained('cashboxes')->restrictOnDelete();
            $table->foreignId('to_cashbox_id')->constrained('cashboxes')->restrictOnDelete();
            $table->foreignId('financial_week_id')->constrained()->restrictOnDelete();
            $table->foreignId('out_transaction_id')->unique()->constrained('cashbox_transactions')->restrictOnDelete();
            $table->foreignId('in_transaction_id')->unique()->constrained('cashbox_transactions')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->dateTime('occurred_at')->index();
            $table->string('reason')->nullable();
            $table->string('status', 24)->default('completed');
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('financial_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_week_id')->constrained()->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index();
            $table->bigInteger('amount');
            $table->text('reason')->nullable();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->dateTime('occurred_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['practitioner_id', 'financial_week_id']);
        });

        Schema::create('weekly_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_week_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashbox_transfer_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedInteger('paid_sessions_count');
            $table->unsignedBigInteger('collections');
            $table->unsignedBigInteger('practitioner_shares');
            $table->unsignedBigInteger('expenses');
            $table->unsignedBigInteger('refunds');
            $table->bigInteger('adjustments');
            $table->bigInteger('calculated_net');
            $table->bigInteger('available_cash');
            $table->unsignedBigInteger('reserved_practitioner_liabilities');
            $table->unsignedBigInteger('transferred_surplus');
            $table->unsignedBigInteger('deficit_covered')->default(0);
            $table->json('practitioner_breakdown')->nullable();
            $table->timestamp('closed_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable()->index();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reopen_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['financial_week_id', 'reopened_at']);
        });

        Schema::create('practitioner_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_week_id')->constrained()->restrictOnDelete();
            $table->bigInteger('opening_balance')->default(0);
            $table->unsignedBigInteger('earnings_amount')->default(0);
            $table->bigInteger('adjustments_amount')->default(0);
            $table->bigInteger('total_due')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->bigInteger('remaining_amount')->default(0);
            $table->string('status', 24)->default('pending');
            $table->timestamps();
            $table->unique(['practitioner_id', 'financial_week_id'], 'practitioner_week_settlement_unique');
        });

        Schema::create('practitioner_settlement_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_settlement_id')
                ->constrained(indexName: 'settlement_payment_settlement_fk')
                ->restrictOnDelete();
            $table->foreignId('cashbox_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->dateTime('paid_at')->index();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('financial_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64)->index();
            $table->nullableMorphs('auditable');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['created_at', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_audit_logs');
        Schema::dropIfExists('practitioner_settlement_payments');
        Schema::dropIfExists('practitioner_settlements');
        Schema::dropIfExists('weekly_closings');
        Schema::dropIfExists('financial_adjustments');
        Schema::dropIfExists('cashbox_transfers');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
