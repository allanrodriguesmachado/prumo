<?php

namespace App\Services\Ai;

use App\Models\Debt;
use App\Models\Goal;
use App\Models\Transaction;
use App\Services\DebtPlanner;
use App\Services\FinanceAnalytics;
use App\Services\GoalPlanner;
use App\Services\ProblemRadar;
use App\Services\TimeMachine;
use Carbon\Carbon;

/**
 * Ferramentas que os modelos de IA podem chamar (function calling nativo).
 * Todas são SOMENTE LEITURA e executam cálculos determinísticos do backend.
 */
class AiTools
{
    public function __construct(private FinanceAnalytics $a) {}

    /** Definições neutras (nome, descrição, JSON Schema dos parâmetros). */
    public static function definitions(): array
    {
        $month = ['type' => 'string', 'description' => 'Mês no formato AAAA-MM. Padrão: mês atual.'];

        return [
            ['name' => 'dinheiro_livre', 'description' => 'Quanto o usuário pode gastar hoje e por dia até o próximo salário, já descontando contas a pagar, faturas, metas, dívidas e poupança. Use para "quanto posso gastar".',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass, 'required' => []]],
            ['name' => 'simular_decisao', 'description' => 'Máquina do Tempo: simula os próximos 12 meses com uma decisão hipotética (compra parcelada/à vista, nova despesa ou renda mensal, perda de renda) e compara com o cenário atual.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'tipo' => ['type' => 'string', 'enum' => ['compra', 'despesa_mensal', 'renda_mensal', 'perda_de_renda']],
                    'valor' => ['type' => 'number', 'description' => 'Valor TOTAL da compra, ou valor mensal nos demais tipos, em reais.'],
                    'parcelas' => ['type' => 'integer', 'description' => 'Número de parcelas da compra (1 = à vista).'],
                    'meses' => ['type' => 'integer', 'description' => 'Por quantos meses a despesa/renda mensal dura (1 a 12).'],
                    'no_cartao' => ['type' => 'boolean', 'description' => 'Se a compra será no cartão de crédito.'],
                ], 'required' => ['tipo', 'valor']]],
            ['name' => 'projecao_saldo', 'description' => 'Projeção do saldo mês a mês com recorrências, parcelas, faturas e média de gastos.',
                'parameters' => ['type' => 'object', 'properties' => ['meses' => ['type' => 'integer', 'description' => '1 a 12. Padrão 3.']], 'required' => []]],
            ['name' => 'gastos_por_categoria', 'description' => 'Gastos por categoria em um mês, com comparação ao mês anterior. Use para "por que gastei mais", "onde estou gastando".',
                'parameters' => ['type' => 'object', 'properties' => ['mes' => $month], 'required' => []]],
            ['name' => 'buscar_lancamentos', 'description' => 'Lista lançamentos (somente leitura) filtrando por texto, categoria, tipo e mês. Máximo 20 itens.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'texto' => ['type' => 'string', 'description' => 'Trecho da descrição, ex.: ifood'],
                    'categoria' => ['type' => 'string', 'description' => 'Nome da categoria, ex.: Mercado'],
                    'tipo' => ['type' => 'string', 'enum' => ['receita', 'despesa']],
                    'mes' => $month,
                    'limite' => ['type' => 'integer', 'description' => 'Até 20.'],
                ], 'required' => []]],
            ['name' => 'plano_dividas', 'description' => 'Compara estratégias de quitação (Prumo, avalanche, bola de neve e só o mínimo): meses até quitar, juros totais e ordem.',
                'parameters' => ['type' => 'object', 'properties' => ['valor_mensal' => ['type' => 'number', 'description' => 'Quanto pagar por mês. Padrão: valor do plano salvo ou soma dos mínimos.']], 'required' => []]],
            ['name' => 'metas_viabilidade', 'description' => 'Metas do usuário com valor mensal necessário e se cabem na capacidade de poupança real.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass, 'required' => []]],
            ['name' => 'radar_problemas', 'description' => 'Alertas de risco ativos: saldo negativo à frente, excesso de parcelas, renda comprometida, vencimentos etc.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass, 'required' => []]],
            ['name' => 'resumo_mes', 'description' => 'Receitas, despesas (fixas, parcelas, dia a dia, dívidas), saldo e regra 50/30/20 de um mês.',
                'parameters' => ['type' => 'object', 'properties' => ['mes' => $month], 'required' => []]],
        ];
    }

    public function execute(string $name, array $args): array
    {
        try {
            return match ($name) {
                'dinheiro_livre' => $this->freeMoney(),
                'simular_decisao' => $this->simulate($args),
                'projecao_saldo' => $this->projection((int) ($args['meses'] ?? 3)),
                'gastos_por_categoria' => $this->categories($args['mes'] ?? null),
                'buscar_lancamentos' => $this->search($args),
                'plano_dividas' => $this->debts($args['valor_mensal'] ?? null),
                'metas_viabilidade' => $this->goals(),
                'radar_problemas' => (new ProblemRadar($this->a))->scan()->map(fn ($r) => ['gravidade' => $r['severity'], 'alerta' => $r['title'], 'detalhe' => $r['text'], 'acao' => $r['action']])->values()->all(),
                'resumo_mes' => $this->month($args['mes'] ?? null),
                default => ['erro' => "Ferramenta desconhecida: {$name}"],
            };
        } catch (\Throwable $e) {
            report($e);

            return ['erro' => 'Não foi possível calcular agora: parâmetros inválidos ou dados insuficientes.'];
        }
    }

    private function monthArg(?string $m): Carbon
    {
        return $m && preg_match('/^\d{4}-\d{2}$/', $m) ? Carbon::createFromFormat('Y-m', $m)->startOfMonth() : now()->startOfMonth();
    }

    private function freeMoney(): array
    {
        $f = $this->a->safeToSpend();

        return [
            'dinheiro_livre_no_periodo' => $f['free'], 'pode_gastar_por_dia' => $f['per_day'], 'ainda_pode_gastar_hoje' => $f['today_left'],
            'gasto_hoje' => $f['spent_today'], 'periodo_ate' => $f['period_end']->format('d/m/Y'), 'dias_restantes' => $f['days_left'],
            'composicao' => collect($f['lines'])->map(fn ($l) => ['item' => $l['label'], 'valor' => round($l['value'], 2)])->all(),
            'premissas' => $f['assumptions'],
        ];
    }

    private function simulate(array $args): array
    {
        $type = ['compra' => 'purchase', 'despesa_mensal' => 'recurring_expense', 'renda_mensal' => 'recurring_income', 'perda_de_renda' => 'income_loss'][$args['tipo'] ?? 'compra'] ?? 'purchase';
        $value = (float) ($args['valor'] ?? 0);
        if ($value <= 0) {
            return ['erro' => 'Informe um valor maior que zero.'];
        }
        $card = ! empty($args['no_cartao']) ? \App\Models\CreditCard::where('archived', false)->first() : null;
        $sim = (new TimeMachine($this->a))->simulate([
            'type' => $type, 'amount' => min($value, 100000000), 'installments' => max(1, min(48, (int) ($args['parcelas'] ?? 1))),
            'months' => max(1, min(12, (int) ($args['meses'] ?? 12))), 'card_id' => $card?->id,
        ]);
        $fmt = fn ($s) => ['cenario' => $s['label'], 'veredito' => $s['verdict'], 'explicacao' => $s['explanation'], 'menor_saldo' => $s['min_balance'], 'mes_do_menor_saldo' => $s['min_month'], 'meses_negativos' => $s['months_negative'], 'saldo_em_12_meses' => $s['end_balance']];

        return ['sem_a_decisao' => $fmt($sim['baseline']), 'cenarios' => collect($sim['scenarios'])->map($fmt)->values()->all(), 'melhor' => $sim['best'], 'premissas' => $sim['assumptions'], 'cartao_usado' => $card?->name];
    }

    private function projection(int $months): array
    {
        return collect($this->a->projection(max(1, min(12, $months))))
            ->map(fn ($r) => ['mes' => $r['label'], 'entradas' => $r['income'], 'saidas' => $r['expense'], 'faturas' => $r['cards'], 'saldo_projetado' => $r['balance']])->all();
    }

    private function categories(?string $m): array
    {
        $month = $this->monthArg($m);
        $cur = $this->a->byCategory($month)->keyBy('name');
        $prev = $this->a->byCategory($month->copy()->subMonthNoOverflow())->keyBy('name');

        return [
            'mes' => $month->format('Y-m'),
            'mes_em_andamento' => $month->isSameMonth(now()) ? now()->day.' de '.now()->daysInMonth.' dias' : 'fechado',
            'categorias' => $cur->keys()->merge($prev->keys())->unique()->map(fn ($n) => [
                'categoria' => $n, 'valor' => $cur[$n]['total'] ?? 0, 'mes_anterior' => $prev[$n]['total'] ?? 0,
                'diferenca' => round(($cur[$n]['total'] ?? 0) - ($prev[$n]['total'] ?? 0), 2), 'orcamento' => $cur[$n]['budget'] ?? null,
            ])->sortByDesc('valor')->values()->all(),
        ];
    }

    private function search(array $args): array
    {
        $q = Transaction::with('category')->orderByDesc('date')
            ->when($args['texto'] ?? null, fn ($q, $v) => $q->where('description', 'ilike', '%'.mb_substr($v, 0, 60).'%'))
            ->when($args['categoria'] ?? null, fn ($q, $v) => $q->whereHas('category', fn ($c) => $c->where('name', 'ilike', '%'.mb_substr($v, 0, 60).'%')))
            ->when(($args['tipo'] ?? null) === 'receita', fn ($q) => $q->where('type', 'income'))
            ->when(($args['tipo'] ?? null) === 'despesa', fn ($q) => $q->where('type', 'expense'))
            ->when($args['mes'] ?? null, fn ($q, $v) => $q->inMonth($this->monthArg($v)));
        $items = $q->take(max(1, min(20, (int) ($args['limite'] ?? 10))))->get();

        return [
            'quantidade' => $items->count(),
            'total' => round((float) $items->sum('amount'), 2),
            'lancamentos' => $items->map(fn ($t) => ['data' => $t->date->format('d/m/Y'), 'descricao' => $t->label(), 'tipo' => $t->type, 'valor' => (float) $t->amount, 'categoria' => $t->category?->name, 'pago' => $t->paid])->all(),
        ];
    }

    private function debts(mixed $budget): array
    {
        $debts = Debt::where('status', '!=', 'paid')->get();
        if ($debts->isEmpty()) {
            return ['mensagem' => 'Nenhuma dívida ativa cadastrada.'];
        }
        $b = (float) ($budget ?: ($this->a->household()->setting('debt_budget') ?: $debts->sum('minimum_payment')));

        return [
            'valor_mensal' => $b,
            'dividas' => $debts->map(fn ($d) => ['credor' => $d->creditor, 'saldo' => (float) $d->current_balance, 'juros_ao_mes' => (float) $d->monthly_interest_rate, 'minimo' => (float) $d->minimum_payment, 'em_atraso' => $d->is_overdue])->all(),
            'estrategias' => collect(app(DebtPlanner::class)->compare($debts, $b))->map(fn ($p, $k) => ['estrategia' => $k, 'meses' => $p['months'], 'juros_totais' => $p['interest'], 'viavel' => $p['feasible'], 'ordem' => $p['order']])->values()->all(),
        ];
    }

    private function goals(): array
    {
        $goals = Goal::all();
        $eval = (new GoalPlanner($this->a))->evaluate($goals);

        return [
            'capacidade_de_poupanca_mensal' => $eval['capacity'],
            'total_necessario_por_mes' => $eval['needed'],
            'metas' => $goals->map(fn ($g) => ['meta' => $g->name, 'alvo' => (float) $g->target_amount, 'guardado' => (float) $g->current_amount, 'prazo' => $g->target_date?->format('m/Y'),
                'por_mes' => $eval['goals'][$g->id]['needed'] ?? null, 'status' => $eval['goals'][$g->id]['status'] ?? ($g->achieved_at ? 'alcançada' : null), 'explicacao' => $eval['goals'][$g->id]['text'] ?? null])->all(),
        ];
    }

    private function month(?string $m): array
    {
        $month = $this->monthArg($m);

        return ['mes' => $month->format('Y-m'), 'resumo' => $this->a->monthSummary($month), 'regra_50_30_20' => $this->a->rule503020($month)];
    }
}
