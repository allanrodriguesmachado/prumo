<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // uma foto por pessoa e carteira: cada um vê o próprio patrimônio (itens privados do parceiro ficam de fora)
        Schema::create('net_worth_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('cash', 14, 2);
            $table->decimal('investments', 14, 2);
            $table->decimal('goals', 14, 2);
            $table->decimal('debts', 14, 2);
            $table->decimal('cards', 14, 2);
            $table->decimal('total', 14, 2);
            $table->boolean('estimated')->default(false); // reconstruída a partir do histórico
            $table->unique(['household_id', 'user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('net_worth_snapshots');
    }
};
