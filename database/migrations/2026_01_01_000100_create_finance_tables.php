<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('individual'); // individual | couple
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('invite_code', 16)->unique();
            $table->string('split_mode', 20)->default('proportional'); // equal | proportional | custom
            $table->unsignedTinyInteger('split_owner_percent')->default(50);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('household_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member'); // owner | member
            $table->timestamps();
            $table->unique(['household_id', 'user_id']);
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('checking'); // checking | savings | wallet | investment | other
            $table->string('bank_code', 10)->nullable();
            $table->string('bank_name')->nullable();
            $table->decimal('initial_balance', 14, 2)->default(0);
            $table->string('color', 9)->default('#0f766e');
            $table->boolean('is_shared')->default(false);
            $table->boolean('archived')->default(false);
            $table->timestamps();
        });

        Schema::create('credit_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('brand', 30)->nullable();
            $table->decimal('limit', 14, 2)->default(0);
            $table->unsignedTinyInteger('closing_day')->default(1);
            $table->unsignedTinyInteger('due_day')->default(10);
            $table->string('color', 9)->default('#7c3aed');
            $table->boolean('is_shared')->default(false);
            $table->boolean('archived')->default(false);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 10); // income | expense
            $table->string('icon', 16)->default('•');
            $table->string('color', 9)->default('#64748b');
            $table->string('group', 20)->default('wants'); // needs | wants | savings (regra 50/30/20)
            $table->decimal('monthly_budget', 14, 2)->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamps();
            $table->index(['household_id', 'type']);
        });

        Schema::create('recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // income | expense
            $table->string('kind', 20); // salary | extra_income | fixed
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credit_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('frequency', 10)->default('monthly'); // weekly | monthly | yearly
            $table->unsignedTinyInteger('day_of_month')->default(5);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('generated_until')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->boolean('auto_paid')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('creditor');
            $table->string('type', 30)->default('personal_loan');
            $table->decimal('original_amount', 14, 2);
            $table->decimal('current_balance', 14, 2);
            $table->decimal('monthly_interest_rate', 7, 3)->default(0); // % a.m.
            $table->decimal('minimum_payment', 14, 2)->default(0);
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->boolean('is_overdue')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->string('status', 20)->default('active'); // active | negotiating | paid
            $table->text('notes')->nullable();
            $table->timestamp('paid_off_at')->nullable();
            $table->timestamps();
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('dream'); // emergency | dream | retirement | purchase | travel
            $table->string('icon', 16)->default('🎯');
            $table->decimal('target_amount', 14, 2);
            $table->decimal('current_amount', 14, 2)->default(0);
            $table->date('target_date')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // income | expense | transfer
            $table->string('kind', 20)->default('daily'); // salary | extra_income | fixed | installment | daily | debt_payment | goal | transfer
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->date('date');
            $table->boolean('paid')->default(true);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transfer_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('credit_card_id')->nullable()->constrained()->nullOnDelete();
            $table->date('invoice_month')->nullable();
            $table->foreignId('recurrence_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('installment_group')->nullable()->index();
            $table->unsignedSmallInteger('installment_number')->nullable();
            $table->unsignedSmallInteger('installment_total')->nullable();
            $table->foreignId('debt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goal_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_shared')->default(false);
            $table->string('source', 20)->default('manual'); // manual | recurrence | import | telegram
            $table->string('import_hash', 64)->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['household_id', 'date']);
            $table->index(['household_id', 'credit_card_id', 'invoice_month']);
        });

        Schema::create('category_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('pattern');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
            $table->unique(['household_id', 'pattern']);
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('format', 10);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('ticker', 30)->nullable();
            $table->string('class', 20); // stock | fii | etf | bdr | crypto | fixed_income | treasury | savings | fund | other
            $table->string('indexer', 20)->nullable(); // cdi | selic | ipca | prefixed
            $table->decimal('rate', 8, 3)->nullable(); // % do CDI, spread IPCA+ ou taxa pré a.a.
            $table->date('maturity_date')->nullable();
            $table->string('institution')->nullable();
            $table->decimal('last_price', 20, 8)->nullable();
            $table->decimal('day_change', 8, 3)->nullable();
            $table->timestamp('price_updated_at')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
        });

        Schema::create('asset_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12); // buy | sell | dividend | jcp | yield
            $table->date('date');
            $table->decimal('quantity', 24, 8)->default(0);
            $table->decimal('price', 20, 8)->default(0);
            $table->decimal('fees', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->timestamps();
        });

        Schema::create('market_indices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20); // cdi | selic | ipca
            $table->date('date');
            $table->decimal('value', 14, 8);
            $table->unique(['code', 'date']);
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('Nova conversa');
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12); // user | assistant
            $table->text('content');
            $table->string('provider', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'ai_messages', 'ai_conversations', 'market_indices', 'asset_operations', 'assets',
            'imports', 'category_rules', 'transactions', 'goals', 'debts', 'recurrences', 'categories',
            'credit_cards', 'accounts', 'household_user', 'households'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
