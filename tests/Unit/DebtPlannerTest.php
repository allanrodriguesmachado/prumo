<?php

namespace Tests\Unit;

use App\Models\Debt;
use App\Services\DebtPlanner;
use PHPUnit\Framework\TestCase;

class DebtPlannerTest extends TestCase
{
    private function debts(): \Illuminate\Support\Collection
    {
        return collect([
            new Debt(['creditor' => 'Rotativo', 'current_balance' => 3000, 'monthly_interest_rate' => 12, 'minimum_payment' => 300]),
            new Debt(['creditor' => 'Loja', 'current_balance' => 500, 'monthly_interest_rate' => 4, 'minimum_payment' => 80]),
            new Debt(['creditor' => 'Consignado', 'current_balance' => 8000, 'monthly_interest_rate' => 1.8, 'minimum_payment' => 400]),
        ]);
    }

    public function test_avalanche_pays_less_interest_than_snowball(): void
    {
        $p = new DebtPlanner;
        $av = $p->simulate($this->debts(), 1500, 'avalanche');
        $sn = $p->simulate($this->debts(), 1500, 'snowball');
        $this->assertTrue($av['feasible']);
        $this->assertLessThanOrEqual($sn['interest'], $av['interest']);
        $this->assertSame('Rotativo', $av['order'][0]);
        $this->assertSame('Loja', $sn['order'][0]);
    }

    public function test_paying_only_minimum_is_slower(): void
    {
        $p = new DebtPlanner;
        $cmp = $p->compare($this->debts(), 1500);
        $this->assertGreaterThan($cmp['prumo']['months'], $cmp['minimum']['months'] ?? 999);
    }

    public function test_infeasible_plan_is_detected(): void
    {
        $plan = (new DebtPlanner)->simulate(collect([new Debt(['creditor' => 'X', 'current_balance' => 10000, 'monthly_interest_rate' => 15, 'minimum_payment' => 100])]), 200);
        $this->assertFalse($plan['feasible']);
    }
}
