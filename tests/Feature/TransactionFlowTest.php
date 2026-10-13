<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Debt;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_installments_split_cents_exactly(): void
    {
        $this->userWithHousehold();
        $this->post(route('transactions.store'), [
            'type' => 'expense', 'kind' => 'installment', 'description' => 'Geladeira', 'amount' => '1.000,00',
            'date' => '2026-03-10', 'installments' => 3, 'installment_value_mode' => 'total', 'paid' => '1',
            'method' => 'account:'.Account::first()->id,
        ])->assertRedirect();

        $parts = Transaction::orderBy('installment_number')->get();
        $this->assertCount(3, $parts);
        $this->assertSame('1000.00', number_format($parts->sum('amount'), 2, '.', ''));
        $this->assertEquals(['333.34', '333.33', '333.33'], $parts->pluck('amount')->all());
        $this->assertEquals(['2026-03-10', '2026-04-10', '2026-05-10'], $parts->map(fn ($t) => $t->date->toDateString())->all());
        $this->assertSame(1, $parts->pluck('installment_group')->unique()->count());
    }

    public function test_card_purchase_lands_on_correct_invoice_and_payment_settles_it(): void
    {
        $this->userWithHousehold();
        $account = Account::first();
        $card = CreditCard::create(['name' => 'Nubank', 'limit' => 5000, 'closing_day' => 3, 'due_day' => 10]);

        $this->assertSame('2026-01-01', $card->invoiceMonthFor(Carbon::parse('2026-01-02'))->toDateString());
        $this->assertSame('2026-02-01', $card->invoiceMonthFor(Carbon::parse('2026-01-03'))->toDateString());

        $late = CreditCard::create(['name' => 'Itaú', 'limit' => 5000, 'closing_day' => 25, 'due_day' => 5]);
        $this->assertSame('2026-02-01', $late->invoiceMonthFor(Carbon::parse('2026-01-20'))->toDateString());
        $this->assertSame('2026-03-01', $late->invoiceMonthFor(Carbon::parse('2026-01-26'))->toDateString());

        $this->post(route('transactions.store'), [
            'type' => 'expense', 'kind' => 'installment', 'description' => 'TV', 'amount' => '600,00', 'date' => '2026-01-05',
            'installments' => 2, 'installment_value_mode' => 'total', 'method' => 'card:'.$card->id,
        ]);
        $this->assertEquals(300.0, $card->invoiceTotal(Carbon::parse('2026-02-01')));
        $this->assertEquals(300.0, $card->invoiceTotal(Carbon::parse('2026-03-01')));
        $this->assertEquals(600.0, $card->usedLimit());

        $before = $account->balance();
        $this->post(route('cards.pay', $card), ['month' => '2026-02', 'account_id' => $account->id])->assertRedirect();
        $this->assertEquals($before - 300, $account->fresh()->balance());
        $this->assertEquals(300.0, $card->usedLimit());
    }

    public function test_transfer_moves_balance_without_counting_as_expense(): void
    {
        $this->userWithHousehold();
        $from = Account::first();
        $from->update(['initial_balance' => 1000]);
        $to = Account::create(['name' => 'Poupança', 'type' => 'savings']);

        $this->post(route('transactions.store'), [
            'type' => 'transfer', 'kind' => 'transfer', 'description' => 'Guardar', 'amount' => '250,00',
            'date' => now()->toDateString(), 'method' => 'account:'.$from->id, 'transfer_account_id' => $to->id,
        ])->assertRedirect();

        $this->assertEquals(750.0, $from->balance());
        $this->assertEquals(250.0, $to->balance());
        $this->assertEquals(0.0, (float) Transaction::spending()->sum('amount'));
    }

    public function test_debt_payment_reduces_balance_and_delete_restores_it(): void
    {
        $this->userWithHousehold();
        $debt = Debt::create(['creditor' => 'Cartão X', 'type' => 'credit_card_revolving', 'original_amount' => 1000, 'current_balance' => 1000, 'monthly_interest_rate' => 12]);

        $this->post(route('debts.pay', $debt), ['amount' => '400,00', 'date' => now()->toDateString()])->assertRedirect();
        $this->assertEquals(600.0, (float) $debt->fresh()->current_balance);

        $this->delete(route('transactions.destroy', Transaction::where('debt_id', $debt->id)->first()));
        $this->assertEquals(1000.0, (float) $debt->fresh()->current_balance);

        $this->post(route('debts.pay', $debt), ['amount' => '1.000,00', 'date' => now()->toDateString()]);
        $this->assertSame('paid', $debt->fresh()->status);
    }

    public function test_toggle_paid(): void
    {
        $this->userWithHousehold();
        $t = Transaction::create(['type' => 'expense', 'kind' => 'fixed', 'description' => 'Luz', 'amount' => 120, 'date' => now()->toDateString(), 'paid' => false]);
        $this->patch(route('transactions.toggle', $t))->assertRedirect();
        $this->assertTrue($t->fresh()->paid);
    }

    public function test_double_submit_is_ignored(): void
    {
        $this->userWithHousehold();
        $payload = ['type' => 'expense', 'kind' => 'daily', 'description' => 'Café', 'amount' => '8,50', 'date' => now()->toDateString()];
        $this->post(route('transactions.store'), $payload)->assertRedirect();
        $this->post(route('transactions.store'), $payload)->assertRedirect();
        $this->assertSame(1, Transaction::count());
    }
}
