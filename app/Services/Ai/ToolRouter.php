<?php

namespace App\Services\Ai;

use App\Models\CreditCard;
use App\Models\Debt;
use App\Services\DebtPlanner;
use App\Services\FinanceAnalytics;
use App\Services\Import\Categorizer;
use App\Services\ProblemRadar;
use App\Services\TimeMachine;

/**
 * Ferramentas determinísticas da IA. A partir da pergunta, executa os
 * cálculos no backend e devolve resultados prontos — a IA apenas explica.
 * Nenhuma ferramenta altera dados.
 */
class ToolRouter
{
    public function __construct(private FinanceAnalytics $a) {}

    /** @return array<int, array{tool: string, description: string, result: array}> */
    public function run(string $question): array
    {
        $q = ' '.Categorizer::normalize($question).' ';
        $raw = mb_strtolower($question);
        $amount = $this->amount($raw);
        $results = [];

        $wantsPurchase = preg_match('/\b(comprar|compra|parcel|assumir|financiar|nova parcela|vale a pena)/', $q);
        if ($wantsPurchase && $amount) {
            $n = $this->installments($raw);
            $isMonthlyParcel = preg_match('/(parcela|presta[cç][aã]o|mensalidade)\s+(de\s+)?r?\$?\s*\d/u', $raw) && ! preg_match('/\d+\s*(x|vezes)/', $raw);
            if ($isMonthlyParcel) {
                $months = preg_match('/(\d+)\s*(meses|mes)/', $q, $m) ? (int) $m[1] : 12;
                $sim = (new TimeMachine($this->a))->simulate(['type' => 'recurring_expense', 'amount' => $amount, 'months' => $months]);
            } else {
                $card = preg_match('/cart[aã]o/u', $raw) ? CreditCard::where('archived', false)->first() : null;
                $sim = (new TimeMachine($this->a))->simulate(['type' => 'purchase', 'amount' => $amount, 'installments' => $n, 'card_id' => $card?->id]);
            }
            $results[] = ['tool' => 'maquina_do_tempo', 'description' => 'Simulação da decisão nos próximos 12 meses', 'result' => $this->compactSimulation($sim)];
        }

        if (preg_match('/(gastar hoje|posso gastar|por dia|dinheiro livre|quanto (eu )?posso|sobra)/', $q)) {
            $f = $this->a->safeToSpend();
            $results[] = ['tool' => 'dinheiro_livre', 'description' => 'Dinheiro livre até o próximo salário', 'result' => [
                'dinheiro_livre' => $f['free'], 'por_dia' => $f['per_day'], 'resta_hoje' => $f['today_left'], 'gasto_hoje' => $f['spent_today'],
                'ate' => $f['period_end']->format('d/m/Y'), 'dias' => $f['days_left'],
                'composicao' => collect($f['lines'])->map(fn ($l) => $l['label'].': '.brl($l['value']))->all(),
            ]];
        }

        if (preg_match('/(proximo mes|proximos meses|saldo .* mes|projec|vai sobrar|vou ficar|fim do mes)/', $q)) {
            $results[] = ['tool' => 'projecao', 'description' => 'Projeção de saldo (contas cadastradas)', 'result' => collect($this->a->projection(4))
                ->map(fn ($r) => ['mes' => $r['label'], 'entradas' => $r['income'], 'saidas' => $r['expense'], 'saldo_projetado' => $r['balance']])->all()];
        }

        if (preg_match('/(por que|porque|pq) .*gast|gastei mais|aumentou|subiu|onde .*gast|comprometend|pesando|gastando demais/', $q)) {
            $cur = $this->a->byCategory(now())->keyBy('name');
            $prev = $this->a->byCategory(now()->subMonthNoOverflow())->keyBy('name');
            $names = $cur->keys()->merge($prev->keys())->unique();
            $results[] = ['tool' => 'comparar_categorias', 'description' => 'Gastos por categoria: mês atual x mês anterior', 'result' => [
                'dias_decorridos_mes_atual' => now()->day.' de '.now()->daysInMonth,
                'categorias' => $names->map(fn ($n) => ['categoria' => $n, 'mes_atual' => $cur[$n]['total'] ?? 0, 'mes_anterior' => $prev[$n]['total'] ?? 0, 'diferenca' => ($cur[$n]['total'] ?? 0) - ($prev[$n]['total'] ?? 0)])
                    ->sortByDesc(fn ($c) => abs($c['diferenca']))->take(8)->values()->all(),
                'gastos_fixos_mensais' => $this->a->monthlyFixedCosts(),
                'parcelas_mes' => $this->a->monthSummary(now())['installments'],
            ]];
        }

        if (preg_match('/(divida|quitar|devendo|juros|negociar|emprestimo|rotativo)/', $q)) {
            $debts = Debt::where('status', '!=', 'paid')->get();
            if ($debts->count()) {
                $budget = (float) ($this->a->household()->setting('debt_budget') ?: $debts->sum('minimum_payment'));
                $cmp = app(DebtPlanner::class)->compare($debts, $budget);
                $results[] = ['tool' => 'plano_dividas', 'description' => 'Comparação de estratégias com '.brl($budget).'/mês', 'result' => collect($cmp)->map(fn ($p, $k) => [
                    'estrategia' => $k, 'meses' => $p['months'], 'juros_totais' => $p['interest'], 'viavel' => $p['feasible'], 'ordem' => $p['order'],
                ])->values()->all()];
            }
        }

        if (preg_match('/(organizar|dividir|distribuir|planejar) .*(salario|renda|dinheiro)/', $q)) {
            $income = $this->a->monthlyIncome();
            $rule = $this->a->rule503020(now());
            $results[] = ['tool' => 'organizar_salario', 'description' => 'Regra 50/30/20 aplicada à renda cadastrada', 'result' => [
                'renda_mensal' => $income,
                'ideal' => ['essenciais_50' => round($income * 0.5, 2), 'estilo_de_vida_30' => round($income * 0.3, 2), 'futuro_20' => round($income * 0.2, 2)],
                'atual' => collect($rule)->map(fn ($g) => ['valor' => round($g['total'], 2), 'percentual' => round($g['pct'], 1)])->all(),
                'gastos_fixos' => $this->a->monthlyFixedCosts(),
            ]];
        }

        // sempre: alertas críticos do radar
        $radar = (new ProblemRadar($this->a))->scan()->whereIn('severity', ['critical', 'warning'])->take(4);
        if ($radar->count()) {
            $results[] = ['tool' => 'radar_de_problemas', 'description' => 'Alertas ativos', 'result' => $radar->map(fn ($r) => $r['title'].': '.$r['text'])->values()->all()];
        }

        return $results;
    }

    private function compactSimulation(array $sim): array
    {
        $fmt = fn ($s) => [
            'cenario' => $s['label'], 'veredito' => $s['verdict'], 'explicacao' => $s['explanation'],
            'menor_saldo' => $s['min_balance'], 'mes_do_menor_saldo' => $s['min_month'], 'meses_negativos' => $s['months_negative'],
            'saldo_final_12m' => $s['end_balance'], 'compromete_da_renda_pct' => $s['commitment_pct'] ?? null,
        ];

        return [
            'sem_a_decisao' => $fmt($sim['baseline']),
            'cenarios' => collect($sim['scenarios'])->map($fmt)->values()->all(),
            'melhor_cenario' => $sim['best'],
            'premissas' => $sim['assumptions'],
        ];
    }

    private function amount(string $text): ?float
    {
        if (preg_match('/r\$\s*([\d.]+(?:,\d{1,2})?)/u', $text, $m) || preg_match('/(\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?|\d+(?:,\d{1,2})?)\s*(reais|mil\b|k\b)/u', $text, $m)) {
            $v = parse_money($m[1]);
            if (isset($m[2]) && in_array(trim($m[2]), ['mil', 'k'])) {
                $v *= 1000;
            }

            return $v > 0 ? $v : null;
        }

        return null;
    }

    private function installments(string $text): int
    {
        if (preg_match('/(\d{1,2})\s*(x|vezes|parcelas)/u', $text, $m)) {
            return max(1, min(48, (int) $m[1]));
        }

        return preg_match('/a vista|à vista/u', $text) ? 1 : 1;
    }
}
