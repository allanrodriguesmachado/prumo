<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHousehold;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recurrence extends Model
{
    use Auditable, BelongsToHousehold;

    public const KINDS = [
        'salary' => 'Salário fixo',
        'extra_income' => 'Renda extra recorrente',
        'fixed' => 'Gasto fixo',
    ];

    public const FREQUENCIES = ['weekly' => 'Semanal', 'monthly' => 'Mensal', 'yearly' => 'Anual'];

    protected $attributes = ['active' => true, 'frequency' => 'monthly', 'is_shared' => false, 'auto_paid' => false];

    protected $fillable = [
        'household_id', 'user_id', 'type', 'kind', 'description', 'amount', 'category_id', 'account_id',
        'credit_card_id', 'frequency', 'day_of_month', 'start_date', 'end_date', 'generated_until',
        'is_shared', 'auto_paid', 'active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date', 'generated_until' => 'date',
            'is_shared' => 'boolean', 'auto_paid' => 'boolean', 'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function creditCard(): BelongsTo { return $this->belongsTo(CreditCard::class); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }

    /** Valor mensal equivalente (para projeções e score). */
    public function monthlyAmount(): float
    {
        return match ($this->frequency) {
            'weekly' => (float) $this->amount * 52 / 12,
            'yearly' => (float) $this->amount / 12,
            default => (float) $this->amount,
        };
    }

    /** Datas de ocorrência entre $from e $to (inclusive). */
    public function occurrencesBetween(Carbon $from, Carbon $to): array
    {
        $dates = [];
        $start = $this->start_date->copy();
        $end = $this->end_date ? Carbon::parse($this->end_date)->min($to) : $to->copy();

        if ($this->frequency === 'weekly') {
            $d = $start->copy();
            while ($d->lte($end)) {
                if ($d->gte($from)) {
                    $dates[] = $d->copy();
                }
                $d->addWeek();
            }

            return $dates;
        }

        $step = $this->frequency === 'yearly' ? 12 : 1;
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $d = $cursor->copy()->day(min($this->day_of_month, $cursor->daysInMonth));
            if ($d->gte($from) && $d->lte($end) && $d->gte($start->copy()->startOfMonth())) {
                $dates[] = $d;
            }
            $cursor->addMonthsNoOverflow($step);
        }

        return $dates;
    }
}
