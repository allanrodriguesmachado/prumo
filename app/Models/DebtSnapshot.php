<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebtSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['household_id', 'date', 'total_balance', 'monthly_interest', 'active_count'];

    protected function casts(): array
    {
        return ['date' => 'date', 'total_balance' => 'float', 'monthly_interest' => 'float'];
    }

    /** Registra (ou atualiza) a foto do dia das dívidas de uma carteira. */
    public static function capture(Household $household): void
    {
        // dívidas privadas ficam fora da foto da carteira (privacidade no casal)
        $debts = $household->debts()->where('status', '!=', 'paid')->where('is_private', false)->get();
        if ($debts->isEmpty() && ! static::where('household_id', $household->id)->exists()) {
            return;
        }
        static::updateOrCreate(['household_id' => $household->id, 'date' => now()->toDateString()], [
            'total_balance' => (float) $debts->sum('current_balance'),
            'monthly_interest' => $debts->sum(fn ($d) => $d->monthlyInterestCost()),
            'active_count' => $debts->count(),
        ]);
    }
}
