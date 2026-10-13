<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHousehold;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCard extends Model
{
    use Auditable, BelongsToHousehold;

    protected $attributes = ['is_private' => false, 'archived' => false, 'is_shared' => false];

    protected $fillable = ['household_id', 'user_id', 'is_private', 'account_id', 'name', 'brand', 'limit', 'closing_day', 'due_day', 'color', 'is_shared', 'archived'];

    protected function casts(): array
    {
        return ['is_private' => 'boolean', 'limit' => 'decimal:2', 'is_shared' => 'boolean', 'archived' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Mês da fatura (1º dia) em que uma compra feita em $date cai.
     * Compras no dia do fechamento ou depois vão para a fatura seguinte.
     * A fatura é identificada pelo mês do vencimento.
     */
    public function invoiceMonthFor(CarbonInterface $date): Carbon
    {
        $date = Carbon::parse($date);
        $closing = min($this->closing_day, $date->daysInMonth);
        $month = $date->copy()->startOfMonth();
        if ($date->day >= $closing) {
            $month->addMonth();
        }
        // se o vencimento é antes do fechamento no calendário, a fatura vence no mês seguinte ao fechamento
        if ($this->due_day <= $this->closing_day) {
            $month->addMonth();
        }

        return $month;
    }

    public function dueDateFor(CarbonInterface $invoiceMonth): Carbon
    {
        $m = Carbon::parse($invoiceMonth)->startOfMonth();

        return $m->day(min($this->due_day, $m->daysInMonth));
    }

    public function invoiceTotal(CarbonInterface $invoiceMonth): float
    {
        return (float) Transaction::withoutGlobalScope('household')
            ->where('credit_card_id', $this->id)
            ->whereDate('invoice_month', Carbon::parse($invoiceMonth)->startOfMonth())
            ->where('type', 'expense')->sum('amount')
            - (float) Transaction::withoutGlobalScope('household')
            ->where('credit_card_id', $this->id)
            ->whereDate('invoice_month', Carbon::parse($invoiceMonth)->startOfMonth())
            ->where('type', 'income')->sum('amount');
    }

    /** Quanto já foi pago (total ou parcialmente) de uma fatura. */
    public function invoicePaid(CarbonInterface $invoiceMonth): float
    {
        return (float) Transaction::withoutGlobalScope('household')->where('kind', 'invoice_payment')
            ->where('settles_card_id', $this->id)->whereDate('invoice_month', Carbon::parse($invoiceMonth)->startOfMonth())->sum('amount');
    }

    /** Saldo em aberto da fatura. Fatura encerrada (todos os itens baixados) não tem saldo — o que faltou foi para a seguinte. */
    public function invoiceRemaining(CarbonInterface $invoiceMonth): float
    {
        $open = Transaction::withoutGlobalScope('household')->where('credit_card_id', $this->id)
            ->whereDate('invoice_month', Carbon::parse($invoiceMonth)->startOfMonth())->where('paid', false)->exists();

        return $open ? round(max(0, $this->invoiceTotal($invoiceMonth) - $this->invoicePaid($invoiceMonth)), 2) : 0.0;
    }

    /** Limite comprometido: tudo que ainda não foi pago (faturas abertas e parcelas futuras). */
    public function usedLimit(): float
    {
        return (float) Transaction::withoutGlobalScope('household')
            ->where('credit_card_id', $this->id)->where('type', 'expense')->where('paid', false)->sum('amount');
    }

    public function availableLimit(): float
    {
        return max(0, (float) $this->limit - $this->usedLimit());
    }
}
