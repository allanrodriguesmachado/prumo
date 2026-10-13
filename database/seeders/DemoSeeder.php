<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\Recurrence;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\RecurrenceService;
use App\Services\TransactionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Conta de DEMONSTRAÇÃO (dados fictícios, claramente identificados).
 * Uso: docker compose exec app php artisan db:seed --class=DemoSeeder
 * Login: demo@prumo.local / demo12345
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'demo@prumo.local')->exists()) {
            $this->command?->warn('Usuário demo já existe.');

            return;
        }
        $user = User::create(['name' => 'Conta Demonstração', 'email' => 'demo@prumo.local', 'password' => 'demo12345', 'onboarded_at' => now(), 'email_verified_at' => now()]);
        $household = app(HouseholdService::class)->createFor($user, 'individual', 'Demonstração (dados fictícios)');
        Auth::setUser($user->fresh());

        $acc = Account::create(['name' => 'Conta corrente (demo)', 'type' => 'checking', 'initial_balance' => 1800, 'color' => '#0f766e']);
        $card = CreditCard::create(['name' => 'Cartão (demo)', 'limit' => 4000, 'closing_day' => 3, 'due_day' => 10, 'account_id' => $acc->id]);
        $cat = fn ($n, $t = 'expense') => Category::where('name', $n)->where('type', $t)->value('id');
        $rec = app(RecurrenceService::class);
        foreach ([
            ['income', 'salary', 'Salário', 4800, 5, 'Salário'],
            ['expense', 'fixed', 'Aluguel', 1400, 10, 'Moradia'],
            ['expense', 'fixed', 'Energia', 180, 15, 'Contas da casa'],
            ['expense', 'fixed', 'Internet', 110, 20, 'Contas da casa'],
            ['expense', 'fixed', 'Academia', 99, 8, 'Saúde'],
        ] as [$type, $kind, $desc, $amount, $day, $c]) {
            $r = Recurrence::create(['type' => $type, 'kind' => $kind, 'description' => $desc, 'amount' => $amount, 'day_of_month' => $day,
                'start_date' => now()->subMonths(3)->startOfMonth(), 'account_id' => $acc->id, 'category_id' => $cat($c, $type), 'auto_paid' => true]);
            $rec->generate($r);
        }
        $tx = app(TransactionService::class);
        foreach (range(0, 75) as $i) {
            $d = now()->subDays($i);
            if ($i % 3 === 0) $tx->create(['type' => 'expense', 'kind' => 'daily', 'description' => 'Mercado', 'amount' => rand(60, 220), 'date' => $d->toDateString(), 'account_id' => $acc->id, 'paid' => true]);
            if ($i % 4 === 0) $tx->create(['type' => 'expense', 'kind' => 'daily', 'description' => 'iFood', 'amount' => rand(35, 90), 'date' => $d->toDateString(), 'credit_card_id' => $card->id]);
            if ($i % 6 === 0) $tx->create(['type' => 'expense', 'kind' => 'daily', 'description' => 'Uber', 'amount' => rand(15, 45), 'date' => $d->toDateString(), 'credit_card_id' => $card->id]);
        }
        foreach ([0, 1, 2] as $m) {
            $tx->create(['type' => 'expense', 'kind' => 'daily', 'description' => 'Netflix', 'amount' => 44.90, 'date' => now()->subMonths($m)->day(12)->toDateString(), 'credit_card_id' => $card->id]);
        }
        $tx->create(['type' => 'expense', 'kind' => 'installment', 'description' => 'Celular', 'amount' => 2400, 'installments' => 10, 'date' => now()->subMonths(2)->toDateString(), 'credit_card_id' => $card->id]);
        $tx->create(['type' => 'income', 'kind' => 'extra_income', 'description' => 'Freela', 'amount' => 650, 'date' => now()->subDays(6)->toDateString(), 'account_id' => $acc->id, 'paid' => true]);
        Category::where('name', 'Mercado')->update(['monthly_budget' => 1100]);
        Category::where('name', 'Restaurantes e delivery')->update(['monthly_budget' => 300]);
        Debt::create(['creditor' => 'Cartão antigo (demo)', 'type' => 'credit_card_revolving', 'original_amount' => 3500, 'current_balance' => 2900, 'monthly_interest_rate' => 12, 'minimum_payment' => 290, 'is_overdue' => true]);
        Debt::create(['creditor' => 'Empréstimo (demo)', 'type' => 'personal_loan', 'original_amount' => 6000, 'current_balance' => 4100, 'monthly_interest_rate' => 4.5, 'minimum_payment' => 380]);
        Goal::create(['name' => 'Reserva de emergência', 'type' => 'emergency', 'icon' => '🛟', 'target_amount' => 12000, 'current_amount' => 1500, 'target_date' => now()->addMonths(18)]);
        $a = Asset::create(['name' => 'Tesouro Selic (demo)', 'class' => 'treasury', 'indexer' => 'selic', 'rate' => 0.1]);
        $a->operations()->create(['type' => 'buy', 'date' => now()->subMonths(8), 'quantity' => 1, 'price' => 2000, 'total' => 2000]);
        $this->command?->info('Demo criada: demo@prumo.local / demo12345');
    }
}
