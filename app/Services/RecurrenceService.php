<?php

namespace App\Services;

use App\Models\CreditCard;
use App\Models\Recurrence;
use App\Models\Transaction;
use Carbon\Carbon;

/**
 * Materializa salários, rendas recorrentes e gastos fixos em lançamentos
 * previstos (pendentes) até o fim do mês seguinte.
 */
class RecurrenceService
{
    public function generate(Recurrence $r, ?Carbon $until = null): int
    {
        if (! $r->active) {
            return 0;
        }
        $until ??= now()->addMonthNoOverflow()->endOfMonth();
        $from = $r->generated_until ? Carbon::parse($r->generated_until)->addDay() : $r->start_date->copy();
        if ($from->gt($until)) {
            return 0;
        }

        $card = $r->credit_card_id ? CreditCard::withoutGlobalScopes()->find($r->credit_card_id) : null;
        $count = 0;
        foreach ($r->occurrencesBetween($from, $until) as $date) {
            $exists = Transaction::withoutGlobalScopes()->where('recurrence_id', $r->id)->whereDate('date', $date)->exists();
            if ($exists) {
                continue;
            }
            Transaction::withoutGlobalScopes()->create([
                'household_id' => $r->household_id,
                'user_id' => $r->user_id,
                'type' => $r->type,
                'kind' => $r->kind === 'extra_income' ? 'extra_income' : $r->kind,
                'description' => $r->description,
                'amount' => $r->amount,
                'date' => $date->toDateString(),
                'paid' => $card ? false : ($r->auto_paid && $date->lte(now())),
                'category_id' => $r->category_id,
                'account_id' => $card ? null : $r->account_id,
                'credit_card_id' => $card?->id,
                'invoice_month' => $card?->invoiceMonthFor($date)->toDateString(),
                'recurrence_id' => $r->id,
                'is_shared' => $r->is_shared,
                'is_private' => (bool) ($card?->is_private || ($r->account_id && \App\Models\Account::withoutGlobalScopes()->whereKey($r->account_id)->value('is_private'))),
                'source' => 'recurrence',
            ]);
            $count++;
        }
        $r->forceFill(['generated_until' => $until->toDateString()])->save();

        return $count;
    }

    /** Ao editar uma recorrência, atualiza os lançamentos futuros ainda não pagos. */
    public function syncFuture(Recurrence $r): void
    {
        Transaction::withoutGlobalScopes()->where('recurrence_id', $r->id)
            ->where('paid', false)->whereDate('date', '>=', now()->toDateString())->delete();
        $r->forceFill(['generated_until' => now()->subDay()->toDateString()])->save();
        $this->generate($r);
    }

    public function generateAll(): int
    {
        $n = 0;
        Recurrence::withoutGlobalScopes()->where('active', true)->each(function ($r) use (&$n) {
            $n += $this->generate($r);
        });

        return $n;
    }
}
