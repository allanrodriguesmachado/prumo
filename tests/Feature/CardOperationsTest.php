<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_payment_carries_remaining_to_next_invoice(): void
    {
        Carbon::setTestNow('2026-02-05');
        $this->userWithHousehold();
        $account = Account::first();
        $card = CreditCard::create(['name' => 'Inter', 'limit' => 3000, 'closing_day' => 3, 'due_day' => 10]);
        $this->post(route('transactions.store'), ['type' => 'expense', 'kind' => 'daily', 'description' => 'Compra', 'amount' => '1.000,00', 'date' => '2026-01-20', 'method' => 'card:'.$card->id]);

        $feb = Carbon::parse('2026-02-01');
        $this->assertEquals(1000.0, $card->invoiceRemaining($feb));
        $this->post(route('cards.pay', $card), ['month' => '2026-02', 'account_id' => $account->id, 'amount' => '400,00'])->assertRedirect();

        $this->assertEquals(0.0, $card->invoiceRemaining($feb));
        $carry = Transaction::where('kind', 'invoice_carry')->first();
        $this->assertEquals(600.0, (float) $carry->amount);
        $this->assertSame('2026-03-01', $carry->invoice_month->toDateString());
        $this->assertEquals(-400.0, $account->balance());
        $this->assertEquals(600.0, $card->usedLimit());
        Carbon::setTestNow();
    }

    public function test_cannot_pay_more_than_remaining(): void
    {
        $this->userWithHousehold();
        $card = CreditCard::create(['name' => 'X', 'limit' => 1000, 'closing_day' => 3, 'due_day' => 10]);
        $this->post(route('cards.pay', $card), ['month' => now()->format('Y-m'), 'account_id' => Account::first()->id])->assertSessionHasErrors('amount');
    }

    public function test_anticipate_installments_with_discount(): void
    {
        Carbon::setTestNow('2026-01-05');
        $this->userWithHousehold();
        $card = CreditCard::create(['name' => 'Nu', 'limit' => 5000, 'closing_day' => 3, 'due_day' => 10]);
        $this->post(route('transactions.store'), ['type' => 'expense', 'kind' => 'installment', 'description' => 'Sofá', 'amount' => '1.200,00', 'installments' => 6, 'installment_value_mode' => 'total', 'date' => '2026-01-05', 'method' => 'card:'.$card->id]);
        $first = Transaction::where('installment_number', 1)->first();

        $this->post(route('cards.anticipate', $first), ['count' => 2, 'discount' => '20,00'])->assertRedirect();
        $current = $card->invoiceMonthFor(now());
        $moved = Transaction::whereIn('installment_number', [5, 6])->get();
        $this->assertTrue($moved->every(fn ($t) => $t->invoice_month->equalTo($current)));
        $this->assertEquals(380.0, (float) $moved->sum('amount'));
        $this->assertEquals(1180.0, $card->usedLimit());
        Carbon::setTestNow();
    }
}
