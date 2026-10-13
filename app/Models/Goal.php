<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    use Auditable, BelongsToHousehold;

    public const TYPES = [
        'emergency' => ['Reserva de emergência', '🛟'],
        'dream' => ['Sonho', '🌟'],
        'travel' => ['Viagem', '✈️'],
        'purchase' => ['Compra planejada', '🛍️'],
        'home' => ['Casa própria', '🏡'],
        'retirement' => ['Aposentadoria / independência', '🌴'],
        'education' => ['Educação', '🎓'],
    ];

    protected $attributes = ['is_private' => false, 'current_amount' => 0, 'is_shared' => false];

    protected $fillable = ['household_id', 'user_id', 'is_private', 'name', 'type', 'icon', 'target_amount', 'current_amount', 'target_date', 'is_shared', 'achieved_at'];

    protected function casts(): array
    {
        return ['is_private' => 'boolean', 'target_amount' => 'decimal:2', 'current_amount' => 'decimal:2', 'target_date' => 'date', 'is_shared' => 'boolean', 'achieved_at' => 'datetime'];
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Transaction::class)->where('kind', 'goal');
    }

    public function progress(): float
    {
        $t = (float) $this->target_amount;

        return $t > 0 ? min(100, (float) $this->current_amount / $t * 100) : 0;
    }

    public function remaining(): float
    {
        return max(0, (float) $this->target_amount - (float) $this->current_amount);
    }

    /** Quanto guardar por mês para chegar na data. */
    public function monthlyNeeded(): ?float
    {
        if (! $this->target_date || $this->remaining() <= 0) {
            return null;
        }
        $months = max(1, (int) ceil(now()->floatDiffInMonths($this->target_date, false)));

        return $this->target_date->isPast() ? $this->remaining() : $this->remaining() / $months;
    }
}
