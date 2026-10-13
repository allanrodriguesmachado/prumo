<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    use Auditable, BelongsToHousehold;

    /** Tipos com juros médios de referência (% a.m.) usados quando o usuário não sabe a taxa. */
    public const TYPES = [
        'credit_card_revolving' => ['Rotativo do cartão', 12.0],
        'overdraft' => ['Cheque especial', 7.5],
        'personal_loan' => ['Empréstimo pessoal', 5.5],
        'store_credit' => ['Crediário / carnê de loja', 5.0],
        'installment_card' => ['Parcelamento de fatura', 8.0],
        'payroll_loan' => ['Consignado', 1.8],
        'vehicle' => ['Financiamento de veículo', 1.9],
        'mortgage' => ['Financiamento imobiliário', 0.9],
        'student' => ['Financiamento estudantil', 0.6],
        'family' => ['Dívida com família/amigos', 0.0],
        'bills' => ['Contas atrasadas (luz, água...)', 2.0],
        'other' => ['Outra', 3.0],
    ];

    protected $attributes = ['is_private' => false, 'status' => 'active', 'is_overdue' => false, 'is_shared' => false, 'minimum_payment' => 0, 'monthly_interest_rate' => 0];

    protected $fillable = [
        'household_id', 'user_id', 'is_private', 'creditor', 'type', 'original_amount', 'current_balance',
        'monthly_interest_rate', 'minimum_payment', 'due_day', 'is_overdue', 'is_shared', 'status', 'notes', 'paid_off_at',
    ];

    protected function casts(): array
    {
        return ['notes' => 'encrypted', 'is_private' => 'boolean', 
            'original_amount' => 'decimal:2', 'current_balance' => 'decimal:2', 'monthly_interest_rate' => 'decimal:3',
            'minimum_payment' => 'decimal:2', 'is_overdue' => 'boolean', 'is_shared' => 'boolean', 'paid_off_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (Debt $d) => ($h = Household::find($d->household_id)) && DebtSnapshot::capture($h));
        static::deleted(fn (Debt $d) => ($h = Household::find($d->household_id)) && DebtSnapshot::capture($h));
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Transaction::class)->where('kind', 'debt_payment');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? 'Dívida';
    }

    public function annualRate(): float
    {
        return (pow(1 + (float) $this->monthly_interest_rate / 100, 12) - 1) * 100;
    }

    /** Juros que a dívida gera por mês hoje. */
    public function monthlyInterestCost(): float
    {
        return (float) $this->current_balance * (float) $this->monthly_interest_rate / 100;
    }

    public function progress(): float
    {
        $orig = (float) $this->original_amount;

        return $orig > 0 ? max(0, min(100, (1 - (float) $this->current_balance / $orig) * 100)) : 0;
    }
}
