<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;

/**
 * Revisão financeira semanal: o que aconteceu nos últimos 7 dias,
 * o que mudou, oportunidades de economia e ações recomendadas.
 */
class WeeklyReview
{
    public function __construct(private FinanceAnalytics $a) {}

    public function build(?Carbon $end = null): array
    {
        $end = ($end ?? now())->copy()->endOfDay();
        $start = $end->copy()->subDays(6)->startOfDay();
        $prevStart = $start->copy()->subDays(7);
        $prevEnd = $start->copy()->subSecond();

        $spent = fn ($from, $to) => (float) Transaction::spending()->whereBetween('date', [$from->toDateString(), $to->toDateString()])->sum('amount');
        $earned = fn ($from, $to) => (float) Transaction::earning()->where('paid', true)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->sum('amount');

        $week = $spent($start, $end);
        $prev = $spent($prevStart, $prevEnd);
        $avg4 = $spent($start->copy()->subDays(28), $start->copy()->subDay()) / 4;
        $daily = (float) Transaction::spending()->where('kind', 'daily')->whereBetween('date', [$start->toDateString(), $end->toDateString()])->sum('amount');

        $byCat = Transaction::spending()->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with('category')->get()->groupBy(fn ($t) => $t->category?->name ?? 'Sem categoria')
            ->map(fn ($g, $name) => ['name' => $name, 'icon' => $g->first()->category?->icon ?? '•', 'total' => (float) $g->sum('amount')])
            ->sortByDesc('total')->values();
        $prevByCat = Transaction::spending()->whereBetween('date', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->with('category')->get()->groupBy(fn ($t) => $t->category?->name ?? 'Sem categoria')->map(fn ($g) => (float) $g->sum('amount'));

        $changes = $byCat->map(fn ($c) => $c + ['prev' => (float) ($prevByCat[$c['name']] ?? 0), 'diff' => $c['total'] - (float) ($prevByCat[$c['name']] ?? 0)])
            ->filter(fn ($c) => abs($c['diff']) >= 30)->sortByDesc(fn ($c) => abs($c['diff']))->take(4)->values();

        $top = Transaction::spending()->with('category')->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderByDesc('amount')->take(5)->get();

        // oportunidades
        $opportunities = [];
        foreach ($this->a->budgets(now()) as $b) {
            if (in_array($b['status'], ['over', 'fast'])) {
                $opportunities[] = "{$b['category']->icon} {$b['category']->name}: ".pct($b['pct'], 0).' do orçamento usado; segure até o fim do mês para economizar até '.brl(max(0, $b['spent'] - $b['budget'] * (now()->day / now()->daysInMonth))).'.';
            }
        }
        $subs = $this->a->detectSubscriptions();
        if ($subs->count()) {
            $opportunities[] = '📺 '.$subs->count().' cobrança(s) recorrente(s) somam '.brl($subs->sum('amount')).'/mês. Cancelar uma de '.brl($subs->first()['amount']).' rende '.brl($subs->first()['yearly']).' por ano.';
        }
        $delivery = $byCat->firstWhere('name', 'Restaurantes e delivery');
        if ($delivery && $delivery['total'] > 100) {
            $opportunities[] = '🍔 Delivery/restaurantes: '.brl($delivery['total']).' na semana. Cozinhar 2 refeições a mais por semana costuma cortar 30% disso.';
        }

        // ações
        $free = $this->a->safeToSpend();
        $actions = [];
        $due = $this->a->upcoming(7)->where('type', 'expense');
        if ($due->count()) {
            $actions[] = 'Pagar '.$due->count().' conta(s) dos próximos 7 dias ('.brl($due->sum('amount')).').';
        }
        if (! $free['negative'] && $free['free'] > 0) {
            $actions[] = 'Seu ritmo seguro é '.brl($free['per_day']).'/dia até '.$free['period_end']->format('d/m').'.';
        } elseif ($free['negative']) {
            $actions[] = 'Dinheiro livre está negativo em '.brl(abs($free['free'])).': congele gastos não essenciais nesta semana.';
        }
        $radar = (new ProblemRadar($this->a))->scan()->where('severity', 'critical')->take(2);
        foreach ($radar as $r) {
            $actions[] = $r['title'].' — '.$r['action'];
        }
        if ($week > $avg4 * 1.2 && $avg4 > 0) {
            $actions[] = 'A semana foi '.pct(($week / $avg4 - 1) * 100, 0).' acima da sua média: revise os maiores gastos abaixo.';
        }

        $tone = $avg4 > 0 ? ($week <= $avg4 ? 'good' : ($week <= $avg4 * 1.2 ? 'neutral' : 'bad')) : 'neutral';
        $headline = match ($tone) {
            'good' => 'Semana sob controle 👏 Você gastou '.brl($week).($avg4 > 0 ? ', '.pct((1 - $week / $avg4) * 100, 0).' abaixo da sua média.' : '.'),
            'bad' => 'Semana acima do ritmo: '.brl($week).' gastos, '.pct(($week / $avg4 - 1) * 100, 0).' acima da média.',
            default => 'Semana dentro do esperado: '.brl($week).' em gastos.',
        };

        return [
            'start' => $start, 'end' => $end, 'spent' => $week, 'earned' => $earned($start, $end), 'prev' => $prev, 'avg4' => round($avg4, 2),
            'daily' => $daily, 'by_category' => $byCat, 'changes' => $changes, 'top' => $top,
            'opportunities' => $opportunities, 'actions' => $actions, 'headline' => $headline, 'tone' => $tone,
            'free' => $free,
        ];
    }

    /** Versão curta para e-mail/Telegram. */
    public function summaryText(array $r): string
    {
        $lines = [$r['headline']];
        if ($r['by_category']->count()) {
            $lines[] = 'Maiores categorias: '.$r['by_category']->take(3)->map(fn ($c) => "{$c['name']} ".brl($c['total']))->implode(', ').'.';
        }
        foreach (array_slice($r['actions'], 0, 3) as $a) {
            $lines[] = '• '.$a;
        }

        return implode("\n", $lines);
    }
}
