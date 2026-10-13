<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AiConversation;
use App\Models\Asset;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\RecurrenceService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Renderiza todas as telas com dados em todos os módulos (individual e casal). */
class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(User $user): void
    {
        $account = Account::first();
        $account->update(['initial_balance' => 2500]);
        $card = CreditCard::create(['name' => 'Nubank', 'limit' => 4000, 'closing_day' => 3, 'due_day' => 10, 'account_id' => $account->id]);
        $salary = Recurrence::create(['type' => 'income', 'kind' => 'salary', 'description' => 'Salário', 'amount' => 5000, 'day_of_month' => 5, 'start_date' => now()->subMonths(3)->startOfMonth(), 'account_id' => $account->id, 'category_id' => Category::where('name', 'Salário')->value('id')]);
        $rent = Recurrence::create(['type' => 'expense', 'kind' => 'fixed', 'description' => 'Aluguel', 'amount' => 1500, 'day_of_month' => 10, 'start_date' => now()->subMonths(3)->startOfMonth(), 'account_id' => $account->id, 'category_id' => Category::where('name', 'Moradia')->value('id'), 'is_shared' => true]);
        app(RecurrenceService::class)->generate($salary);
        app(RecurrenceService::class)->generate($rent);
        $svc = app(TransactionService::class);
        $svc->create(['type' => 'expense', 'kind' => 'daily', 'description' => 'iFood pizza', 'amount' => 62.5, 'date' => now()->toDateString(), 'paid' => true, 'account_id' => $account->id, 'is_shared' => true]);
        $svc->create(['type' => 'expense', 'kind' => 'installment', 'description' => 'Notebook', 'amount' => 3600, 'installments' => 12, 'date' => now()->subMonth()->toDateString(), 'credit_card_id' => $card->id]);
        $svc->create(['type' => 'income', 'kind' => 'extra_income', 'description' => 'Freela', 'amount' => 800, 'date' => now()->toDateString(), 'paid' => true, 'account_id' => $account->id]);
        Category::where('name', 'Mercado')->update(['monthly_budget' => 900]);
        Debt::create(['creditor' => 'Banco Rotativo', 'type' => 'credit_card_revolving', 'original_amount' => 4000, 'current_balance' => 3200, 'monthly_interest_rate' => 12, 'minimum_payment' => 320, 'is_overdue' => true]);
        Debt::create(['creditor' => 'Loja', 'type' => 'store_credit', 'original_amount' => 900, 'current_balance' => 0, 'monthly_interest_rate' => 5, 'status' => 'paid', 'paid_off_at' => now()]);
        Goal::create(['name' => 'Reserva', 'type' => 'emergency', 'target_amount' => 18000, 'current_amount' => 2000, 'target_date' => now()->addYear()]);
        $stock = Asset::create(['name' => 'Petrobras', 'ticker' => 'PETR4', 'class' => 'stock', 'last_price' => 38.5]);
        $stock->operations()->create(['type' => 'buy', 'date' => now()->subMonths(2), 'quantity' => 100, 'price' => 35, 'total' => 3500]);
        $stock->operations()->create(['type' => 'dividend', 'date' => now()->subMonth(), 'quantity' => 0, 'price' => 0, 'total' => 120]);
        $cdb = Asset::create(['name' => 'CDB Banco', 'class' => 'fixed_income', 'indexer' => 'cdi', 'rate' => 110]);
        $cdb->operations()->create(['type' => 'buy', 'date' => now()->subYear(), 'quantity' => 1, 'price' => 5000, 'total' => 5000]);
        $conv = AiConversation::create(['user_id' => $user->id, 'household_id' => $user->current_household_id, 'title' => 'Teste']);
        $conv->messages()->create(['role' => 'user', 'content' => 'Como saio das dívidas?']);
        $conv->messages()->create(['role' => 'assistant', 'content' => '**Plano:** pague o rotativo primeiro.', 'provider' => 'offline']);
    }

    private function routes(): array
    {
        $tx = Transaction::first();

        return [
            route('dashboard'), route('setup'),
            route('transactions.index'), route('transactions.create'), route('transactions.create', ['tipo' => 'income']), route('transactions.edit', $tx),
            route('transactions.index', ['busca' => 'pizza', 'tipo' => 'expense', 'status' => 'pago']),
            route('recurrences.index'), route('recurrences.create'), route('recurrences.create', ['tipo' => 'salary']), route('recurrences.edit', Recurrence::first()),
            route('accounts.index'), route('accounts.create'), route('accounts.show', Account::first()), route('accounts.edit', Account::first()),
            route('cards.index'), route('cards.create'), route('cards.edit', CreditCard::first()), route('cards.invoice', CreditCard::first()),
            route('categories.index'), route('categories.create'), route('categories.edit', Category::first()),
            route('budgets.index'),
            route('debts.index'), route('debts.create'), route('debts.edit', Debt::first()), route('debts.negotiation', Debt::first()),
            route('goals.index'), route('goals.create'), route('goals.create', ['tipo' => 'emergency']), route('goals.edit', Goal::first()),
            route('investments.index'), route('investments.create'), route('investments.create', ['classe' => 'fixed_income']),
            route('investments.show', Asset::first()), route('investments.edit', Asset::first()), route('investments.show', Asset::latest('id')->first()), route('investments.simulators'),
            route('ai.index'), route('ai.index', AiConversation::first()),
            route('reports.index'), route('calendar.index'), route('imports.index'),
            route('time-machine.index'), route('time-machine.index', ['type' => 'purchase', 'amount' => '2.000,00', 'installments' => 10, 'card_id' => CreditCard::first()->id]),
            route('time-machine.index', ['type' => 'recurring_expense', 'amount' => '150,00', 'months' => 6]),
            route('time-machine.index', ['type' => 'income_loss', 'amount' => '1.000,00']),
            route('radar.index'), route('weekly.index'), route('two-factor.setup'),
            route('couple.index'), route('profile.edit'),
        ];
    }

    public function test_all_pages_render_for_individual_with_data(): void
    {
        $user = $this->userWithHousehold();
        $this->seedData($user);
        foreach ($this->routes() as $url) {
            $res = $this->get($url);
            $this->assertSame(200, $res->getStatusCode(), "Falhou: {$url}\n".($res->exception ? $res->exception->getMessage().' @ '.$res->exception->getFile().':'.$res->exception->getLine() : ''));
        }
    }

    public function test_all_pages_render_for_couple(): void
    {
        $ana = $this->userWithHousehold('couple');
        $leo = User::factory()->create();
        app(HouseholdService::class)->join($leo, $this->household($ana)->invite_code);
        $this->seedData($ana);
        $this->actingAs($leo->fresh());
        // conversas com a IA são privadas de cada pessoa, mesmo dentro do casal
        $this->get(route('ai.index', AiConversation::first()))->assertNotFound();
        foreach (array_filter($this->routes(), fn ($u) => ! preg_match('#/consultor/\d+#', $u)) as $url) {
            $res = $this->get($url);
            $this->assertSame(200, $res->getStatusCode(), "Falhou: {$url}\n".($res->exception ? $res->exception->getMessage().' @ '.$res->exception->getFile().':'.$res->exception->getLine() : ''));
        }
    }

    public function test_empty_pages_render(): void
    {
        $this->userWithHousehold();
        foreach ([route('dashboard'), route('transactions.index'), route('debts.index'), route('goals.index'), route('investments.index'), route('reports.index'), route('calendar.index'), route('couple.index'), route('budgets.index'), route('recurrences.index'), route('radar.index'), route('weekly.index'), route('time-machine.index'), route('cards.index'), route('accounts.index'), route('ai.index')] as $url) {
            $res = $this->get($url);
            $this->assertSame(200, $res->getStatusCode(), "Falhou: {$url}\n".($res->exception ? $res->exception->getMessage().' @ '.$res->exception->getFile().':'.$res->exception->getLine() : ''));
        }
    }

    public function test_guest_pages_render(): void
    {
        foreach (['/', '/entrar', '/cadastro', '/esqueci-senha', '/redefinir-senha/abc'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
