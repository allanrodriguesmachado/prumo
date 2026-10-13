<?php

namespace App\Services;

use App\Models\CreditCard;
use Carbon\Carbon;

/**
 * Máquina do Tempo Financeira: compara a projeção atual com cenários
 * hipotéticos (compra parcelada, à vista, esperar e juntar, nova despesa ou
 * renda recorrente) para os próximos meses. Nada é gravado no banco.
 */
class TimeMachine
{
    public const MONTHS = 12;

    public function __construct(private FinanceAnalytics $analytics) {}

    /**
     * @param  array{type: string, amount: float, installments?: int, card_id?: int|null, start?: string|null, months?: int|null, description?: string}  $input
     */
    public function simulate(array $input): array
    {
        $type = $input['type'];
        $amount = round((float) $input['amount'], 2);
        $start = isset($input['start']) && $input['start'] ? Carbon::parse($input['start'])->startOfMonth() : now()->startOfMonth();
        $baseline = $this->analytics->projection(self::MONTHS);
        $income = $this->analytics->monthlyIncome();
        $scenarios = [];

        if ($type === 'purchase') {
            $n = max(1, min(48, (int) ($input['installments'] ?? 1)));
            $card = ! empty($input['card_id']) ? CreditCard::find($input['card_id']) : null;
            // no cartão, a 1ª parcela cai na fatura do mês seguinte à compra (em geral)
            $first = $card ? $card->invoiceMonthFor(now()->max($start)) : $start->copy();
            $scenarios['parcelado'] = $this->scenario(
                $n > 1 ? "Parcelado em {$n}x de ".brl($amount / $n) : 'Pagamento único',
                $this->spread($first, $amount, $n), $baseline, $income,
                $n > 1 ? 'Parcelas sem juros (informe o valor total com juros, se houver).' : null
            );
            if ($n > 1) {
                $scenarios['a_vista'] = $this->scenario('À vista agora', $this->spread($start, $amount, 1), $baseline, $income);
            }
            // esperar e juntar: guardar o mesmo valor da parcela até ter o total, e comprar à vista
            $monthly = $n > 1 ? $amount / $n : max($amount / 6, 1);
            $wait = (int) ceil($amount / $monthly);
            $saving = [];
            for ($i = 0; $i < $wait; $i++) {
                $saving[$start->copy()->addMonthsNoOverflow($i)->format('Y-m')]['expense'] = $monthly;
            }
            // o dinheiro guardado sai do caixa, mas volta como compra à vista (efeito líquido: igual ao guardado)
            $scenarios['juntar'] = $this->scenario("Juntar ".brl($monthly)."/mês e comprar em ".month_name($start->copy()->addMonthsNoOverflow($wait - 1), true).'/'.$start->copy()->addMonthsNoOverflow($wait - 1)->format('y'),
                $saving, $baseline, $income, 'Sem considerar o rendimento do dinheiro guardado (que seria a seu favor) nem possíveis descontos à vista.');
        } elseif ($type === 'recurring_expense' || $type === 'recurring_income') {
            $months = (int) ($input['months'] ?? 0) ?: self::MONTHS;
            $key = $type === 'recurring_income' ? 'income' : 'expense';
            $adj = [];
            for ($i = 0; $i < $months; $i++) {
                $adj[$start->copy()->addMonthsNoOverflow($i)->format('Y-m')][$key] = $amount;
            }
            $label = ($key === 'income' ? 'Nova renda de ' : 'Nova despesa de ').brl($amount).'/mês'.($input['months'] ?? null ? " por {$months} meses" : '');
            $scenarios['cenario'] = $this->scenario($label, $adj, $baseline, $income);
        } elseif ($type === 'income_loss') {
            $adj = [];
            for ($i = 0; $i < self::MONTHS; $i++) {
                $adj[$start->copy()->addMonthsNoOverflow($i)->format('Y-m')]['income'] = -$amount;
            }
            $scenarios['cenario'] = $this->scenario('Perda de renda de '.brl($amount).'/mês', $adj, $baseline, $income);
        }

        return [
            'baseline' => $this->summarize('Sem a decisão (hoje)', $baseline, $income),
            'scenarios' => $scenarios,
            'best' => collect($scenarios)->sortByDesc(fn ($s) => [$s['months_negative'] === 0 ? 1 : 0, $s['min_balance']])->keys()->first(),
            'assumptions' => [
                'Parte do saldo atual das contas cadastradas e dos lançamentos previstos (salários, fixos, parcelas e faturas).',
                'Gastos do dia a dia seguem a média dos últimos 3 meses.',
                'Dívidas cadastradas consomem ao menos o pagamento mínimo a partir do próximo mês.',
                'É uma projeção, não uma garantia: imprevistos e mudanças de renda alteram o resultado.',
            ],
        ];
    }

    private function spread(Carbon $first, float $amount, int $n): array
    {
        $out = [];
        foreach (\App\Support\Money::split($amount, $n) as $i => $part) {
            $out[$first->copy()->addMonthsNoOverflow($i)->format('Y-m')]['expense'] = $part;
        }

        return $out;
    }

    private function scenario(string $label, array $adj, array $baseline, float $income, ?string $note = null): array
    {
        $rows = $this->analytics->projection(self::MONTHS, $adj);
        $s = $this->summarize($label, $rows, $income);
        $s['note'] = $note;
        $s['delta_end'] = round($s['end_balance'] - end($baseline)['balance'], 2);
        $maxExtra = 0;
        foreach ($adj as $a) {
            $maxExtra = max($maxExtra, (float) ($a['expense'] ?? 0));
        }
        $s['commitment_pct'] = $income > 0 ? round($maxExtra / $income * 100, 1) : null;

        return $s;
    }

    private function summarize(string $label, array $rows, float $income): array
    {
        $min = collect($rows)->sortBy('balance')->first();
        $neg = collect($rows)->where('balance', '<', 0);
        $verdict = match (true) {
            $neg->count() > 0 => ['inviável', 'rose', 'O saldo fica negativo em '.$neg->count().' mês(es), a partir de '.$neg->first()['label'].'.'],
            $min['balance'] < max(500, $income * 0.2) => ['apertado', 'amber', 'O saldo fica baixo (mínimo de '.brl($min['balance']).' em '.$min['label'].'). Qualquer imprevisto vira dívida.'],
            default => ['viável', 'emerald', 'O saldo segue positivo com folga em todo o período.'],
        };

        return [
            'label' => $label,
            'rows' => $rows,
            'min_balance' => $min['balance'],
            'min_month' => $min['label'],
            'months_negative' => $neg->count(),
            'end_balance' => end($rows)['balance'],
            'verdict' => $verdict[0], 'color' => $verdict[1], 'explanation' => $verdict[2],
        ];
    }
}
