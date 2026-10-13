<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HouseholdService
{
    public function createFor(User $user, string $type = 'individual', ?string $name = null): Household
    {
        return DB::transaction(function () use ($user, $type, $name) {
            $household = Household::create([
                'name' => $name ?: ($type === 'couple' ? 'Finanças do casal' : 'Minhas finanças'),
                'type' => $type,
                'owner_id' => $user->id,
            ]);
            $household->members()->attach($user->id, ['role' => 'owner']);

            foreach (Category::defaults() as [$n, $t, $icon, $color, $group]) {
                Category::withoutGlobalScopes()->create([
                    'household_id' => $household->id, 'name' => $n, 'type' => $t, 'icon' => $icon, 'color' => $color, 'group' => $group,
                ]);
            }

            Account::withoutGlobalScopes()->create([
                'household_id' => $household->id, 'user_id' => $user->id, 'name' => 'Carteira', 'type' => 'wallet', 'color' => '#ca8a04',
            ]);

            $user->forceFill(['current_household_id' => $household->id])->save();

            return $household;
        });
    }

    public function join(User $user, string $code): ?Household
    {
        $household = Household::where('invite_code', strtoupper(trim($code)))->first();
        if (! $household) {
            return null;
        }
        if (! $household->members()->where('users.id', $user->id)->exists()) {
            if ($household->members()->count() >= 2) {
                return null;
            }
            $household->members()->attach($user->id, ['role' => 'member']);
            $household->update(['type' => 'couple']);
        }
        $user->forceFill(['current_household_id' => $household->id])->save();

        return $household;
    }
}
