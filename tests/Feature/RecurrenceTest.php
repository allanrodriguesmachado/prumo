<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurrenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_salary_generates_until_next_month_without_duplicates(): void
    {
        Carbon::setTestNow('2026-10-09');
        $this->userWithHousehold();
        $this->post(route('recurrences.store'), [
            'kind' => 'salary', 'description' => 'Salário', 'amount' => '5.000,00', 'frequency' => 'monthly',
            'day_of_month' => 31, 'start_date' => '2026-08-01', 'method' => 'account:'.Account::first()->id, 'auto_paid' => '1',
        ])->assertRedirect();

        $dates = Transaction::orderBy('date')->pluck('date')->map->toDateString()->all();
        // dia 31 vira o último dia em meses curtos
        $this->assertSame(['2026-08-31', '2026-09-30', '2026-10-31', '2026-11-30'], $dates);
        $this->assertTrue(Transaction::whereDate('date', '2026-09-30')->first()->paid);
        $this->assertFalse(Transaction::whereDate('date', '2026-10-31')->first()->paid);

        // rodar o gerador de novo não duplica
        app(RecurrenceService::class)->generateAll();
        $this->assertSame(4, Transaction::count());
        Carbon::setTestNow();
    }

    public function test_editing_recurrence_updates_only_future_pending(): void
    {
        Carbon::setTestNow('2026-10-09');
        $this->userWithHousehold();
        $r = Recurrence::create(['type' => 'expense', 'kind' => 'fixed', 'description' => 'Aluguel', 'amount' => 1000, 'day_of_month' => 10, 'start_date' => '2026-09-01']);
        app(RecurrenceService::class)->generate($r);
        Transaction::whereDate('date', '2026-09-10')->update(['paid' => true]);

        $this->put(route('recurrences.update', $r), [
            'kind' => 'fixed', 'description' => 'Aluguel', 'amount' => '1.200,00', 'frequency' => 'monthly', 'day_of_month' => 10, 'start_date' => '2026-09-01', 'active' => '1',
        ])->assertRedirect();

        $this->assertEquals(1000, (float) Transaction::whereDate('date', '2026-09-10')->value('amount'));
        $this->assertEquals(1200, (float) Transaction::whereDate('date', '2026-10-10')->value('amount'));
        $this->assertEquals(1200, (float) Transaction::whereDate('date', '2026-11-10')->value('amount'));
        Carbon::setTestNow();
    }
}
