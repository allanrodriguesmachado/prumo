<?php

namespace App\Services;

use App\Models\Recurrence;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Radar de Problemas: antecipa riscos a partir de regras explícitas.
 * Cada alerta traz severidade, explicação com números e ação sugerida.
 */
class ProblemRadar
{
    public function __construct(private FinanceAnalytics $a) {}

    public function scan(): Collection
    {
        $out = collect();
        $income = $this->a->monthlyIncome();
        $projection = $this->a->projection(6);

        // 1. saldo negativo à frente
        $neg = collect($projection)->firstWhere('balance', '<', 0);
        if ($neg) {
            $out->push($this->alert('negative_balance', 'critical', '📉', 'Risco de saldo negativo em '.$neg['label'],
                'A projeção indica saldo de '.brl($neg['balance']).' considerando contas fixas, parcelas, faturas e sua média de gastos.',
                'Simule cortes ou adie compras na Máquina do Tempo.', route('time-machine.index')));
        }

        // 2. excesso de parcelas futuras
        $installments = (float) Transaction::where('kind', 'installment')->where('paid', false)
            ->whereBetween('date', [now()->startOfMonth()->addMonth()->toDateString(), now()->addMonths(3)->endOfMonth()->toDateString()])->sum('amount') / 3;
        if ($income > 0 && $installments / $income > 0.25) {
            $out->push($this->alert('installments', $installments / $income > 0.4 ? 'critical' : 'warning', '🧾', 'Parcelas comprometem '.pct($installments / $income * 100, 0).' da renda',
                'Nos próximos 3 meses, as parcelas somam em média '.brl($installments).'/mês. Acima de 25% a renda fica engessada.',
                'Evite novos parcelamentos até algumas terminarem — veja quando no Radar de parcelas.', route('reports.index').'#radar'));
        }

        // 3. comprometimento alto da renda (fixos + parcelas + dívidas)
        $debtMin = (float) \App\Models\Debt::where('status', '!=', 'paid')->sum('minimum_payment');
        $committed = $this->a->monthlyFixedCosts() + $installments + $debtMin;
        if ($income > 0 && $committed / $income > 0.7) {
            $out->push($this->alert('commitment', $committed / $income > 0.9 ? 'critical' : 'warning', '⚖️', 'Renda '.pct($committed / $income * 100, 0).' comprometida',
                'Gastos fixos ('.brl($this->a->monthlyFixedCosts()).'), parcelas ('.brl($installments).') e mínimos de dívidas ('.brl($debtMin).') somam '.brl($committed).' por mês.',
                'Revise contratos fixos e use o GPS Financeiro para reorganizar as dívidas.', route('debts.index')));
        }

        // 4. queda de renda
        $last = (float) Transaction::earning()->inMonth(now()->subMonthNoOverflow())->sum('amount');
        $prevAvg = (float) Transaction::earning()->whereBetween('date', [now()->subMonths(4)->startOfMonth(), now()->subMonths(2)->endOfMonth()])->sum('amount') / 3;
        if ($prevAvg > 0 && $last < $prevAvg * 0.8) {
            $out->push($this->alert('income_drop', 'warning', '💼', 'Renda caiu '.pct((1 - $last / $prevAvg) * 100, 0).' no último mês',
                'Recebido no mês passado: '.brl($last).' contra média de '.brl($prevAvg).' nos 3 meses anteriores.',
                'Ajuste os orçamentos para a nova realidade antes que o cartão compense a diferença.', route('budgets.index')));
        }

        // 5. aumento forte de categoria
        $cur = $this->a->byCategory(now())->keyBy('name');
        $avg = Transaction::spending()->whereBetween('date', [now()->subMonths(3)->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->selectRaw('category_id, sum(amount)/3 as avg')->groupBy('category_id')->pluck('avg', 'category_id');
        foreach ($cur as $name => $c) {
            $base = (float) ($avg[$c['id']] ?? 0);
            $elapsed = now()->day / now()->daysInMonth;
            if ($base > 100 && $c['total'] > $base * max(0.5, $elapsed) * 1.4) {
                $out->push($this->alert('category_spike', 'warning', $c['icon'], "{$name} acima do normal",
                    'Este mês já são '.brl($c['total']).', contra média de '.brl($base).'/mês e o mês ainda não acabou.',
                    'Veja os lançamentos dessa categoria.', route('transactions.index', ['categoria' => $c['id']])));
                break;
            }
        }

        // 6. recorrência com valor fora do padrão (ex.: conta de luz que dobrou)
        foreach (Recurrence::where('type', 'expense')->where('active', true)->get() as $r) {
            $paid = Transaction::where('recurrence_id', $r->id)->whereBetween('date', [now()->subMonths(2)->startOfMonth(), now()->endOfMonth()])->orderByDesc('date')->first();
            if ($paid && (float) $paid->amount > (float) $r->amount * 1.3) {
                $out->push($this->alert('recurring_unusual', 'warning', '🔁', "{$r->description} veio mais alto",
                    'Último valor: '.brl($paid->amount).' contra '.brl($r->amount).' habitual (+'.pct(((float) $paid->amount / (float) $r->amount - 1) * 100, 0).').',
                    'Confira a cobrança ou atualize o valor do gasto fixo.', route('recurrences.edit', $r)));
            }
        }
        foreach ($this->a->detectSubscriptions()->take(3) as $sub) {
            $known = Recurrence::where('description', 'ilike', $sub['description'])->exists();
            if (! $known && $sub['months'] >= 3) {
                $out->push($this->alert('subscription', 'info', '📺', "Cobrança recorrente: {$sub['description']}",
                    'Aparece todo mês ('.brl($sub['amount']).', '.brl($sub['yearly']).'/ano) e não está cadastrada como gasto fixo.',
                    'Cancele se não usa, ou cadastre como fixo para entrar nas projeções.', route('recurrences.create', ['tipo' => 'fixed'])));
            }
        }

        // 7. vencimentos próximos
        $due = $this->a->upcoming(3)->where('type', 'expense');
        if ($due->count()) {
            $overdue = $due->where('overdue', true)->count();
            $out->push($this->alert('due_soon', $overdue ? 'critical' : 'info', '⏰', $overdue ? "{$overdue} conta(s) vencida(s)" : $due->count().' conta(s) vencem em até 3 dias',
                $due->take(3)->map(fn ($d) => $d['label'].' ('.$d['date']->format('d/m').', '.brl($d['amount']).')')->implode('; ').'.',
                'Pague ou marque como pago para manter as projeções corretas.', route('calendar.index')));
        }

        // 8. dinheiro livre negativo
        $free = $this->a->safeToSpend();
        if ($free['negative']) {
            $out->push($this->alert('free_money', 'critical', '🧯', 'Dinheiro livre negativo',
                'Depois de separar contas, faturas, metas e dívidas até '.$free['period_end']->format('d/m').', faltam '.brl(abs($free['free'])).'.',
                'Priorize as contas essenciais e adie metas e compras não urgentes.', route('dashboard')));
        }

        $order = ['critical' => 0, 'warning' => 1, 'info' => 2];

        return $out->sortBy(fn ($x) => $order[$x['severity']])->values();
    }

    private function alert(string $code, string $severity, string $icon, string $title, string $text, string $action, string $url): array
    {
        return compact('code', 'severity', 'icon', 'title', 'text', 'action', 'url');
    }
}
