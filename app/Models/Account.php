<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use Auditable, BelongsToHousehold;

    public const TYPES = [
        'checking' => 'Conta corrente',
        'savings' => 'Poupança',
        'wallet' => 'Carteira / dinheiro',
        'investment' => 'Conta investimento',
        'benefit' => 'Vale-refeição / benefício',
        'other' => 'Outra',
    ];

    protected $attributes = ['is_private' => false, 'initial_balance' => 0, 'archived' => false, 'is_shared' => false, 'type' => 'checking'];

    protected $fillable = ['household_id', 'user_id', 'is_private', 'name', 'type', 'bank_code', 'bank_name', 'initial_balance', 'color', 'is_shared', 'archived'];

    protected function casts(): array
    {
        return ['is_private' => 'boolean', 'initial_balance' => 'decimal:2', 'is_shared' => 'boolean', 'archived' => 'boolean'];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Saldo realizado até hoje (lançamentos pagos, sem cartão de crédito). */
    public function balance(?string $until = null, bool $includePending = false): float
    {
        $until ??= now()->toDateString();
        $base = Transaction::withoutGlobalScope('household')->whereDate('date', '<=', $until)->whereNull('credit_card_id')
            ->when(! $includePending, fn ($q) => $q->where('paid', true));

        $in = (clone $base)->where('account_id', $this->id)->where('type', 'income')->sum('amount');
        $out = (clone $base)->where('account_id', $this->id)->whereIn('type', ['expense', 'transfer'])->sum('amount');
        $transferIn = (clone $base)->where('transfer_account_id', $this->id)->where('type', 'transfer')->sum('amount');

        return round((float) $this->initial_balance + $in - $out + $transferIn, 2);
    }

    /** Saldo previsto: inclui lançamentos pendentes (contas a pagar/receber) até a data. */
    public function projectedBalance(string $until): float
    {
        return $this->balance($until, true);
    }
}
