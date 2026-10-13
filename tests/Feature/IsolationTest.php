<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Debt;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Garante que um usuário nunca acessa dados de outra carteira. */
class IsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_see_or_edit_other_household_records(): void
    {
        $alice = $this->userWithHousehold();
        $tx = Transaction::create(['type' => 'expense', 'kind' => 'daily', 'description' => 'Segredo da Alice', 'amount' => 50, 'date' => now()->toDateString()]);
        $debt = Debt::create(['creditor' => 'Banco X', 'type' => 'other', 'original_amount' => 100, 'current_balance' => 100]);

        $bob = $this->userWithHousehold();

        $this->get(route('transactions.edit', $tx))->assertNotFound();
        $this->put(route('transactions.update', $tx), ['type' => 'expense', 'kind' => 'daily', 'description' => 'hack', 'amount' => '1', 'date' => now()->toDateString()])->assertNotFound();
        $this->delete(route('transactions.destroy', $tx))->assertNotFound();
        $this->get(route('debts.edit', $debt))->assertNotFound();
        $this->get(route('transactions.index'))->assertOk()->assertDontSee('Segredo da Alice');

        $this->assertDatabaseHas('transactions', ['id' => $tx->id, 'description' => 'Segredo da Alice']);
    }

    public function test_cannot_reference_foreign_account_in_new_transaction(): void
    {
        $alice = $this->userWithHousehold();
        $aliceAccount = Account::first();
        $this->userWithHousehold();

        $this->post(route('transactions.store'), [
            'type' => 'expense', 'kind' => 'daily', 'description' => 'x', 'amount' => '10,00',
            'date' => now()->toDateString(), 'method' => 'account:'.$aliceAccount->id,
        ])->assertSessionHasErrors('account_id');
    }

    public function test_cannot_switch_to_household_user_is_not_member_of(): void
    {
        $alice = $this->userWithHousehold();
        $aliceHousehold = $alice->current_household_id;
        $this->userWithHousehold();
        $this->post(route('household.switch', $aliceHousehold))->assertNotFound();
    }
}
