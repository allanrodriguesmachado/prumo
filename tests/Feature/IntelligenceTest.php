<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Services\Ai\ToolRouter;
use App\Services\FinanceAnalytics;
use App\Services\FreeMoneyService;
use App\Services\GoalPlanner;
use App\Services\ProblemRadar;
use App\Services\RecurrenceService;
use App\Services\TimeMachine;
use App\Services\WeeklyReview;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private function setupFinances(float $balance = 3000): FinanceAnalytics
    {
        Carbon::setTestNow('2026-10-09 10:00');
        $user = $this->userWithHousehold();
        $acc = Account::first();
        $acc->update(['initial_balance' => $balance, 'type' => 'checking']);
        $salary = Recurrence::create(['type' => 'income', 'kind' => 'salary', 'description' => 'Salário', 'amount' => 5000, 'day_of_month' => 5, 'start_date' => '2026-10-01', 'account_id' => $acc->id]);
        $rent = Recurrence::create(['type' => 'expense', 'kind' => 'fixed', 'description' => 'Aluguel', 'amount' => 1500, 'day_of_month' => 15, 'start_date' => '2026-10-01', 'account_id' => $acc->id]);
        app(RecurrenceService::class)->generate($salary);
        app(RecurrenceService::class)->generate($rent);
        // salário de outubro já recebido e refletido no saldo inicial
        Transaction::whereDate('date', '2026-10-05')->delete();

        return new FinanceAnalytics($this->household($user));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_free_money_separates_bills_cards_goals_and_savings(): void
    {
        $a = $this->setupFinances(3000);
        $h = $a->household();
        $h->putSetting('saving_target_pct', 0);
        $card = CreditCard::create(['name' => 'Nu', 'limit' => 5000, 'closing_day' => 1, 'due_day' => 20]);
        Transaction::create(['type' => 'expense', 'kind' => 'daily', 'description' => 'Compra', 'amount' => 400, 'date' => '2026-10-02', 'credit_card_id' => $card->id, 'invoice_month' => '2026-10-01', 'paid' => false]);
        $goal = Goal::create(['name' => 'Viagem', 'target_amount' => 1200, 'target_date' => '2027-04-15']);
        $this->assertEqualsWithDelta(1200 / 7, $goal->monthlyNeeded(), 0.01, '6,2 meses até a data → 7 parcelas mensais');

        $f = (new FreeMoneyService($h))->calculate();
        // período: 09/10 até 04/11 (véspera do próximo salário)
        $this->assertSame('2026-11-04', $f['period_end']->toDateString());
        $this->assertSame(27, $f['days_left']);
        // 3000 − aluguel 1500 − fatura 400 − reserva da meta (1200/7)
        $expected = 3000 - 1500 - 400 - 1200 / 7;
        $this->assertEqualsWithDelta($expected, $f['free'], 0.01);
        $this->assertEqualsWithDelta($expected / 27, $f['per_day'], 0.01);
        $this->assertFalse($f['negative']);
    }

    public function test_free_money_negative_when_commitments_exceed_cash(): void
    {
        $a = $this->setupFinances(500);
        $f = (new FreeMoneyService($a->household()))->calculate();
        $this->assertTrue($f['negative']);
        $this->assertSame(0.0, $f['per_day']);
    }

    public function test_time_machine_verdicts(): void
    {
        $a = $this->setupFinances(3000);
        $ok = (new TimeMachine($a))->simulate(['type' => 'purchase', 'amount' => 1000, 'installments' => 10]);
        $this->assertSame('viável', $ok['scenarios']['parcelado']['verdict']);
        $this->assertArrayHasKey('a_vista', $ok['scenarios']);
        $this->assertArrayHasKey('juntar', $ok['scenarios']);
        $this->assertEqualsWithDelta(-1000, $ok['scenarios']['parcelado']['delta_end'], 0.01);

        $bad = (new TimeMachine($a))->simulate(['type' => 'recurring_expense', 'amount' => 6000, 'months' => 6]);
        $this->assertSame('inviável', $bad['scenarios']['cenario']['verdict']);
        $this->assertGreaterThan(0, $bad['scenarios']['cenario']['months_negative']);
    }

    public function test_radar_detects_negative_projection_and_overdue(): void
    {
        $a = $this->setupFinances(200);
        Transaction::create(['type' => 'expense', 'kind' => 'fixed', 'description' => 'Luz atrasada', 'amount' => 300, 'date' => '2026-10-01', 'paid' => false]);
        Debt::create(['creditor' => 'Banco', 'type' => 'personal_loan', 'original_amount' => 9000, 'current_balance' => 9000, 'monthly_interest_rate' => 5, 'minimum_payment' => 4500]);
        $codes = (new ProblemRadar($a))->scan()->pluck('code');
        $this->assertContains('negative_balance', $codes);
        $this->assertContains('due_soon', $codes);
        $this->assertContains('commitment', $codes);
        $this->assertContains('free_money', $codes);
    }

    public function test_goal_planner_flags_infeasible_goal(): void
    {
        $a = $this->setupFinances(3000);
        $g = Goal::create(['name' => 'Carro', 'target_amount' => 60000, 'target_date' => '2027-04-01']);
        $eval = (new GoalPlanner($a))->evaluate(collect([$g]));
        $this->assertSame('inviável no prazo', $eval['goals'][$g->id]['status']);
        $this->assertTrue($eval['overbooked']);
        $this->assertNotNull($eval['goals'][$g->id]['realistic_date']);
    }

    public function test_tool_router_runs_deterministic_simulation_for_purchase_question(): void
    {
        $a = $this->setupFinances(3000);
        $tools = collect((new ToolRouter($a))->run('Se eu comprar algo de R$ 2.000 parcelado em 10 vezes, como fico?'))->keyBy('tool');
        $this->assertTrue($tools->has('maquina_do_tempo'));
        $cen = $tools['maquina_do_tempo']['result']['cenarios'][0];
        $this->assertStringContainsString('10x', $cen['cenario']);

        $tools = collect((new ToolRouter($a))->run('Quanto posso gastar hoje?'))->keyBy('tool');
        $this->assertTrue($tools->has('dinheiro_livre'));

        $tools = collect((new ToolRouter($a))->run('Consigo assumir uma nova parcela de R$ 300 por 12 meses?'))->keyBy('tool');
        $this->assertStringContainsString('300', $tools['maquina_do_tempo']['result']['cenarios'][0]['cenario']);
    }

    public function test_ai_endpoint_answers_offline_with_calculated_numbers(): void
    {
        $this->setupFinances(3000);
        $res = $this->postJson(route('ai.send'), ['message' => 'Se eu comprar uma TV de R$ 2.000 em 10x, como ficam minhas finanças?'])->assertOk();
        $this->assertSame('offline', $res->json('provider'));
        $this->assertStringContainsString('Simulei essa decisão', $res->json('content'));
        $this->assertStringContainsString('10x', $res->json('content'));
        // a IA nunca altera dados
        $this->assertSame(0, Transaction::where('description', 'like', '%TV%')->count());
    }

    public function test_weekly_review_compares_weeks(): void
    {
        $a = $this->setupFinances(3000);
        Transaction::create(['type' => 'expense', 'kind' => 'daily', 'description' => 'Mercado', 'amount' => 200, 'date' => '2026-10-08']);
        Transaction::create(['type' => 'expense', 'kind' => 'daily', 'description' => 'Mercado', 'amount' => 100, 'date' => '2026-10-01']);
        $r = (new WeeklyReview($a))->build();
        $this->assertEquals(200.0, $r['spent']);
        $this->assertEquals(100.0, $r['prev']);
        $this->assertNotEmpty($r['headline']);
    }
}
