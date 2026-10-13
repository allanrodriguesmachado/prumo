<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\NetWorthSnapshot;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\NetWorthHistory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetWorthHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_is_idempotent_and_backfill_reconstructs_month_ends(): void
    {
        Carbon::setTestNow('2026-10-09');
        $user = $this->userWithHousehold();
        $acc = Account::first();
        $acc->update(['initial_balance' => 1000]);
        Transaction::create(['type' => 'income', 'kind' => 'salary', 'description' => 'Salário', 'amount' => 3000, 'date' => '2026-08-05', 'account_id' => $acc->id]);
        Transaction::create(['type' => 'expense', 'kind' => 'daily', 'description' => 'Gasto', 'amount' => 500, 'date' => '2026-09-10', 'account_id' => $acc->id]);
        $h = $this->household($user);
        $svc = app(NetWorthHistory::class);

        $svc->capture($user, $h);
        $svc->capture($user, $h);
        $this->assertSame(1, NetWorthSnapshot::whereDate('date', '2026-10-09')->count());
        $this->assertEquals(3500.0, NetWorthSnapshot::whereDate('date', '2026-10-09')->value('total'));

        $svc->backfill($user, $h, 3);
        $this->assertEquals(4000.0, NetWorthSnapshot::whereDate('date', '2026-08-31')->value('cash'));
        $this->assertEquals(3500.0, NetWorthSnapshot::whereDate('date', '2026-09-30')->value('cash'));
        $this->assertTrue(NetWorthSnapshot::whereDate('date', '2026-09-30')->first()->estimated);
        $this->assertCount(3, $svc->series($user, $h));
        Carbon::setTestNow();
    }

    public function test_partner_snapshot_excludes_my_private_accounts(): void
    {
        $ana = $this->userWithHousehold('couple');
        Account::create(['name' => 'Reserva secreta', 'initial_balance' => 10000, 'is_private' => true]);
        $leo = User::factory()->create();
        app(HouseholdService::class)->join($leo, $this->household($ana)->invite_code);
        $h = $this->household($ana);
        $svc = app(NetWorthHistory::class);

        $this->assertEquals(10000.0, $svc->capture($ana, $h)->cash);
        $this->assertEquals(0.0, $svc->capture($leo->fresh(), $h)->cash);
    }
}
