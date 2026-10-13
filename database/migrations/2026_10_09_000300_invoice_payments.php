<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pagamento de fatura: a saída é da conta, mas fica vinculada ao cartão/fatura que ela quita
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('settles_card_id')->nullable()->after('credit_card_id')->constrained('credit_cards')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', fn (Blueprint $t) => $t->dropConstrainedForeignId('settles_card_id'));
    }
};
