<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHousehold;
use App\Services\Market\FixedIncomeCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use Auditable, BelongsToHousehold;

    public const CLASSES = [
        'stock' => ['Ações', '#2563eb', 'variable'],
        'fii' => ['Fundos imobiliários', '#0d9488', 'variable'],
        'etf' => ['ETFs', '#7c3aed', 'variable'],
        'bdr' => ['BDRs / exterior', '#db2777', 'variable'],
        'crypto' => ['Criptomoedas', '#f59e0b', 'crypto'],
        'fixed_income' => ['Renda fixa (CDB, LCI, LCA...)', '#16a34a', 'fixed'],
        'treasury' => ['Tesouro Direto', '#0891b2', 'fixed'],
        'savings' => ['Poupança', '#65a30d', 'fixed'],
        'fund' => ['Fundos de investimento', '#9333ea', 'manual'],
        'other' => ['Outros', '#64748b', 'manual'],
    ];

    public const INDEXERS = ['cdi' => '% do CDI', 'selic' => 'Selic +', 'ipca' => 'IPCA +', 'prefixed' => 'Prefixado (% a.a.)', 'savings' => 'Regra da poupança'];

    protected $fillable = [
        'household_id', 'user_id', 'is_private', 'name', 'ticker', 'class', 'indexer', 'rate', 'maturity_date', 'institution',
        'last_price', 'day_change', 'price_updated_at', 'is_shared',
    ];

    protected function casts(): array
    {
        return ['is_private' => 'boolean', 
            'rate' => 'float', 'last_price' => 'float', 'day_change' => 'float', 'maturity_date' => 'date',
            'price_updated_at' => 'datetime', 'is_shared' => 'boolean',
        ];
    }

    public function operations(): HasMany
    {
        return $this->hasMany(AssetOperation::class)->orderBy('date')->orderBy('id');
    }

    public function classLabel(): string { return self::CLASSES[$this->class][0] ?? 'Outros'; }
    public function classColor(): string { return self::CLASSES[$this->class][1] ?? '#64748b'; }
    public function pricing(): string { return self::CLASSES[$this->class][2] ?? 'manual'; }
    public function isMarketPriced(): bool { return in_array($this->pricing(), ['variable', 'crypto']) && $this->ticker; }

    /**
     * Posição consolidada: quantidade, preço médio, custo, valor atual, proventos e rentabilidade.
     */
    public function position(): array
    {
        $ops = $this->relationLoaded('operations') ? $this->operations : $this->operations()->get();
        $qty = 0.0; $cost = 0.0; $income = 0.0; $realized = 0.0; $invested = 0.0;

        foreach ($ops as $op) {
            $q = (float) $op->quantity; $t = (float) $op->total;
            switch ($op->type) {
                case 'buy':
                    $qty += $q; $cost += $t; $invested += $t;
                    break;
                case 'sell':
                    $avg = $qty > 0 ? $cost / $qty : 0;
                    $sellQty = min($q, $qty);
                    $realized += $t - $avg * $sellQty;
                    $cost -= $avg * $sellQty; $qty -= $sellQty;
                    break;
                default:
                    $income += $t;
            }
        }

        if ($this->pricing() === 'fixed') {
            $value = app(FixedIncomeCalculator::class)->valueOf($this, $ops);
        } elseif ($this->pricing() === 'manual') {
            $value = $this->last_price !== null && $qty > 0 ? (float) $this->last_price : $cost;
        } elseif ($this->last_price !== null && $qty > 0) {
            $value = $qty * (float) $this->last_price;
        } else {
            $value = $cost;
        }

        $gain = $value - $cost + $income + $realized;

        return [
            'quantity' => $qty,
            'avg_price' => $qty > 0 ? $cost / $qty : 0,
            'cost' => round($cost, 2),
            'value' => round($value, 2),
            'income' => round($income, 2),
            'realized' => round($realized, 2),
            'gain' => round($gain, 2),
            'gain_pct' => $cost > 0 ? $gain / $cost * 100 : 0,
            'invested' => $invested,
        ];
    }
}
