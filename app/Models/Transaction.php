<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use Auditable, BelongsToHousehold;

    public const KINDS = [
        'salary' => 'Salário',
        'extra_income' => 'Renda extra',
        'fixed' => 'Gasto fixo',
        'installment' => 'Parcelamento',
        'daily' => 'Gasto do dia a dia',
        'debt_payment' => 'Pagamento de dívida',
        'goal' => 'Aporte em meta',
        'invoice_payment' => 'Pagamento de fatura',
        'invoice_carry' => 'Saldo de fatura (rotativo)',
        'transfer' => 'Transferência',
        'other_income' => 'Outra receita',
    ];

    protected $attributes = ['is_private' => false, 'paid' => true, 'is_shared' => false, 'source' => 'manual'];

    protected $fillable = [
        'household_id', 'user_id', 'is_private', 'type', 'kind', 'description', 'amount', 'date', 'paid', 'category_id',
        'account_id', 'transfer_account_id', 'credit_card_id', 'settles_card_id', 'invoice_month', 'reconciled_at', 'recurrence_id',
        'installment_group', 'installment_number', 'installment_total', 'debt_id', 'goal_id',
        'is_shared', 'source', 'import_hash', 'notes', 'approval_status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return ['notes' => 'encrypted', 'is_private' => 'boolean', 
            'amount' => 'decimal:2', 'date' => 'date', 'invoice_month' => 'date',
            'paid' => 'boolean', 'is_shared' => 'boolean', 'reconciled_at' => 'datetime', 'approved_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function transferAccount(): BelongsTo { return $this->belongsTo(Account::class, 'transfer_account_id'); }
    public function creditCard(): BelongsTo { return $this->belongsTo(CreditCard::class); }
    public function recurrence(): BelongsTo { return $this->belongsTo(Recurrence::class); }
    public function debt(): BelongsTo { return $this->belongsTo(Debt::class); }
    public function goal(): BelongsTo { return $this->belongsTo(Goal::class); }

    /** Despesas "de consumo" (exclui pagamento de fatura para não contar em dobro). */
    public function scopeSpending(Builder $q): Builder
    {
        return $q->where($q->qualifyColumn('type'), 'expense')->where($q->qualifyColumn('kind'), '!=', 'invoice_payment');
    }

    public function scopeEarning(Builder $q): Builder
    {
        return $q->where($q->qualifyColumn('type'), 'income')->whereNull($q->qualifyColumn('credit_card_id'));
    }

    public function scopeInMonth(Builder $q, $month): Builder
    {
        $m = \Carbon\Carbon::parse($month);

        return $q->whereBetween($q->qualifyColumn('date'), [$m->copy()->startOfMonth()->toDateString(), $m->copy()->endOfMonth()->toDateString()]);
    }

    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    /** Despesas compartilhadas que contam no acerto do casal (aprovadas ou que não exigiam aprovação). */
    public function scopeSettleable(Builder $q): Builder
    {
        return $q->where($q->qualifyColumn('is_shared'), true)
            ->where(fn ($w) => $w->whereNull($q->qualifyColumn('approval_status'))->orWhere($q->qualifyColumn('approval_status'), 'approved'));
    }

    public function label(): string
    {
        $suffix = $this->installment_total ? " ({$this->installment_number}/{$this->installment_total})" : '';

        return $this->description.$suffix;
    }
}
