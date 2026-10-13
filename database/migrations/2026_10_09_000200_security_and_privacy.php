<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('event', 40);
            $table->string('auditable_type', 60)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['auditable_type', 'auditable_id']);
        });

        // Privacidade no casal: itens privados não aparecem para o(a) parceiro(a)
        foreach (['accounts', 'credit_cards', 'transactions', 'assets', 'goals', 'debts'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->boolean('is_private')->default(false));
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('reconciled_at')->nullable();
        });

        // histórico do GPS Financeiro
        Schema::create('debt_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('total_balance', 14, 2);
            $table->decimal('monthly_interest', 14, 2);
            $table->unsignedSmallInteger('active_count');
            $table->unique(['household_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_snapshots');
        Schema::table('transactions', fn (Blueprint $t) => $t->dropColumn('reconciled_at'));
        foreach (['accounts', 'credit_cards', 'transactions', 'assets', 'goals', 'debts'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn('is_private'));
        }
        Schema::dropIfExists('audit_logs');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']));
    }
};
