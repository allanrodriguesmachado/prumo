<?php

namespace App\Models\Concerns;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Isola os dados por "carteira" (household). Em requisições autenticadas toda
 * consulta é filtrada pela carteira ativa do usuário, e novos registros são
 * vinculados a ela automaticamente.
 */
trait BelongsToHousehold
{
    public const PRIVATE_TABLES = ['accounts', 'credit_cards', 'transactions', 'assets', 'goals', 'debts'];

    public static function bootBelongsToHousehold(): void
    {
        static::addGlobalScope('household', function (Builder $query) {
            if ($id = static::activeHouseholdId()) {
                $table = $query->getModel()->getTable();
                $query->where($table.'.household_id', $id);
                // itens privados só aparecem para quem os criou (privacidade no casal)
                if (in_array($table, static::PRIVATE_TABLES, true)) {
                    $query->where(fn ($q) => $q->where($table.'.is_private', false)->orWhere($table.'.user_id', Auth::id()));
                }
            }
        });

        static::creating(function ($model) {
            if (! $model->household_id && ($id = static::activeHouseholdId())) {
                $model->household_id = $id;
            }
            if (in_array('user_id', $model->getFillable()) && ! $model->user_id && Auth::id()) {
                $model->user_id = Auth::id();
            }
        });
    }

    protected static function activeHouseholdId(): ?int
    {
        $user = Auth::user();

        return $user instanceof User ? $user->current_household_id : null;
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
