<?php

namespace App\Services\Ai;

use App\Models\Debt;
use App\Models\Goal;
use App\Models\Recurrence;
use App\Models\User;
use App\Services\DebtPlanner;
use App\Services\FinanceAnalytics;

/**
 * Monta um retrato financeiro compacto (sem dados sensíveis como e-mail ou
 * nomes de contas bancárias) para dar contexto à IA.
 */
class FinancialContext
{
    public function build(User $user): array
    {
        $a = FinanceAnalytics::current();
        $h = $a->household();
        $month = $a->monthSummary(now());
        $prev = $a->monthSummary(now()->subMonthNoOverflow());
        $score = $a->score();
        $debts = Debt::where('status', '!=', 'paid')->get();
        $budget = (float) $h->setting('debt_budget', 0);

        return [
            'perfil' => $h->isCouple() ? 'casal (finanças compartilhadas)' : 'individual',
            'primeiro_nome' => $user->firstName(),
            'renda_mensal_estimada' => $a->monthlyIncome(),
            'salarios_e_rendas_fixas' => Recurrence::where('type', 'income')->where('active', true)->get()->map(fn ($r) => "{$r->description}: ".brl($r->monthlyAmount()))->all(),
            'gastos_fixos_mensais' => $a->monthlyFixedCosts(),
            'gasto_medio_mensal_3m' => $a->averageMonthlySpending(),
            'mes_atual' => $month,
            'mes_anterior' => $prev,
            'gastos_por_categoria_mes' => $a->byCategory(now())->take(10)->map(fn ($c) => "{$c['name']}: ".brl($c['total']).($c['budget'] ? ' (orçamento '.brl($c['budget']).')' : ''))->all(),
            'regra_50_30_20' => collect($a->rule503020(now()))->map(fn ($g) => pct($g['pct']).' (ideal '.$g['ideal'].'%)')->all(),
            'saldo_em_contas' => $a->totalBalance(),
            'patrimonio' => $a->netWorth(),
            'pode_gastar_por_dia' => $a->safeToSpend(),
            'prumo_score' => $score['total'].'/1000 ('.$score['level'].')',
            'pilares_score' => collect($score['pillars'])->map(fn ($p) => "{$p['label']}: {$p['value']}/{$p['max']} — {$p['hint']}")->values()->all(),
            'dividas' => $debts->map(fn ($d) => [
                'credor' => $d->creditor, 'tipo' => $d->typeLabel(), 'saldo' => (float) $d->current_balance,
                'juros_am' => (float) $d->monthly_interest_rate, 'parcela_minima' => (float) $d->minimum_payment,
                'em_atraso' => $d->is_overdue, 'status' => $d->status,
            ])->all(),
            'plano_dividas' => $budget > 0 && $debts->count() ? collect(app(DebtPlanner::class)->simulate($debts, $budget, $h->setting('debt_strategy', 'prumo')))->only(['months', 'interest', 'order', 'feasible'])->all() + ['valor_mensal' => $budget] : null,
            'metas' => Goal::get()->map(fn ($g) => "{$g->name}: ".brl($g->current_amount).' de '.brl($g->target_amount).($g->target_date ? ' até '.$g->target_date->format('m/Y') : ''))->all(),
            'assinaturas_detectadas' => $a->detectSubscriptions()->take(8)->map(fn ($s) => "{$s['description']}: ".brl($s['amount']).'/mês')->all(),
            'parcelamentos_ativos' => $a->installmentRadar()->take(10)->map(fn ($p) => "{$p['description']}: ".brl($p['amount'])." x {$p['remaining']} restantes")->all(),
        ];
    }

    public function asText(User $user): string
    {
        return json_encode($this->build($user), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
    }
}
