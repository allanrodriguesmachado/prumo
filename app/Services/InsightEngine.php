<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\Goal;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Gera alertas e dicas automáticas (sem IA externa) a partir dos dados.
 */
class InsightEngine
{
    public function __construct(private FinanceAnalytics $a) {}

    public function generate(): Collection
    {
        $out = collect();
        $now = now();
        $month = $this->a->monthSummary($now);
        $prev = $this->a->monthSummary($now->copy()->subMonthNoOverflow());

        if ($month['income'] > 0 && $month['balance'] < 0) {
            $out->push(['level' => 'danger', 'icon' => '🚨', 'title' => 'Mês no vermelho', 'text' => 'As despesas previstas superam as receitas em '.brl(abs($month['balance'])).'.', 'link' => route('transactions.index')]);
        }

        foreach ($this->a->budgets($now) as $b) {
            if ($b['status'] === 'over') {
                $out->push(['level' => 'danger', 'icon' => $b['category']->icon, 'title' => "Orçamento estourado: {$b['category']->name}", 'text' => 'Gasto de '.brl($b['spent']).' para um teto de '.brl($b['budget']).'.', 'link' => route('budgets.index')]);
            } elseif ($b['status'] === 'fast') {
                $out->push(['level' => 'warning', 'icon' => $b['category']->icon, 'title' => "{$b['category']->name} acelerado", 'text' => 'Já usou '.pct($b['pct'], 0).' do orçamento e o mês ainda não acabou.', 'link' => route('budgets.index')]);
            }
        }

        // categoria que mais cresceu
        $cur = $this->a->byCategory($now)->keyBy('name');
        $old = $this->a->byCategory($now->copy()->subMonthNoOverflow())->keyBy('name');
        $jump = $cur->map(fn ($c, $name) => ['name' => $name, 'icon' => $c['icon'], 'diff' => $c['total'] - ($old[$name]['total'] ?? 0), 'old' => $old[$name]['total'] ?? 0])
            ->filter(fn ($c) => $c['old'] > 50 && $c['diff'] > $c['old'] * 0.3)->sortByDesc('diff')->first();
        if ($jump) {
            $out->push(['level' => 'warning', 'icon' => $jump['icon'], 'title' => "{$jump['name']} subiu ".pct($jump['diff'] / $jump['old'] * 100, 0), 'text' => 'Comparado ao mês passado, são '.brl($jump['diff']).' a mais.', 'link' => route('reports.index')]);
        }

        $overdue = Debt::where('is_overdue', true)->where('status', '!=', 'paid')->count();
        if ($overdue) {
            $out->push(['level' => 'danger', 'icon' => '⛓️', 'title' => "{$overdue} dívida(s) em atraso", 'text' => 'Monte seu plano de saída e negocie — o Prumo mostra a ordem ideal de pagamento.', 'link' => route('debts.index')]);
        }
        $expensive = Debt::where('status', '!=', 'paid')->where('monthly_interest_rate', '>', 6)->get();
        if ($expensive->count()) {
            $out->push(['level' => 'warning', 'icon' => '🔥', 'title' => 'Juros abusivos', 'text' => $expensive->pluck('creditor')->implode(', ').' cobram mais de 6% ao mês ('.brl($expensive->sum(fn ($d) => $d->monthlyInterestCost())).' de juros/mês).', 'link' => route('debts.index')]);
        }

        $late = Transaction::whereNull('credit_card_id')->where('type', 'expense')->where('paid', false)->whereDate('date', '<', $now->toDateString())->count();
        if ($late) {
            $out->push(['level' => 'warning', 'icon' => '⏰', 'title' => "{$late} conta(s) vencida(s) sem baixa", 'text' => 'Marque como pagas ou pague para evitar multa e juros.', 'link' => route('calendar.index')]);
        }

        $subs = $this->a->detectSubscriptions();
        if ($subs->count() >= 2) {
            $out->push(['level' => 'tip', 'icon' => '📺', 'title' => 'Assinaturas recorrentes', 'text' => $subs->count().' cobranças repetidas somam '.brl($subs->sum('amount')).'/mês ('.brl($subs->sum('yearly')).'/ano). Ainda usa todas?', 'link' => route('reports.index')]);
        }

        if (! Goal::where('type', 'emergency')->exists()) {
            $out->push(['level' => 'tip', 'icon' => '🛟', 'title' => 'Crie sua reserva de emergência', 'text' => 'É o primeiro degrau da independência financeira. Comece com qualquer valor.', 'link' => route('goals.index')]);
        }

        if ($prev['income'] > 0 && $prev['savings_rate'] >= 20) {
            $out->push(['level' => 'success', 'icon' => '🏆', 'title' => 'Mês passado foi ótimo', 'text' => 'Você poupou '.pct($prev['savings_rate'], 0).' da renda. Continue assim!', 'link' => route('reports.index')]);
        }

        $sts = $this->a->safeToSpend();
        if (! $sts['negative'] && $sts['spent_today'] > $sts['per_day'] * 1.5 && $sts['per_day'] > 0) {
            $out->push(['level' => 'warning', 'icon' => '💸', 'title' => 'Dia acima do ritmo', 'text' => 'Hoje você gastou '.brl($sts['spent_today']).', acima do ideal de '.brl($sts['per_day']).'/dia.', 'link' => route('transactions.index')]);
        }

        return $out->take(8);
    }
}
