<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Household extends Model
{
    protected $fillable = ['name', 'type', 'owner_id', 'invite_code', 'split_mode', 'split_owner_percent', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'split_owner_percent' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (Household $h) {
            $h->invite_code ??= strtoupper(Str::random(8));
        });
    }

    public function isCouple(): bool
    {
        return $this->type === 'couple';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public const ROLES = [
        'owner' => 'Titular (tudo, inclusive configurações)',
        'member' => 'Membro (lança, edita e aprova)',
        'viewer' => 'Somente leitura',
    ];

    public function roleOf(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return $this->members()->where('users.id', $user->id)->value('household_user.role');
    }

    public function canWrite(?User $user): bool
    {
        return in_array($this->roleOf($user), ['owner', 'member'], true);
    }

    /** Valor a partir do qual uma despesa compartilhada precisa da aprovação do(a) parceiro(a). 0 = desligado. */
    public function approvalThreshold(): float
    {
        return $this->isCouple() ? (float) $this->setting('approval_threshold', 0) : 0.0;
    }

    public function partnerOf(User $user): ?User
    {
        return $this->members->firstWhere('id', '!=', $user->id);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->settings ?? [], $key, $default);
    }

    public function putSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        Arr::set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    // Relações sem o escopo global (usadas por jobs/console)
    public function transactions(): HasMany { return $this->hasMany(Transaction::class)->withoutGlobalScope('household'); }
    public function accounts(): HasMany { return $this->hasMany(Account::class)->withoutGlobalScope('household'); }
    public function creditCards(): HasMany { return $this->hasMany(CreditCard::class)->withoutGlobalScope('household'); }
    public function categories(): HasMany { return $this->hasMany(Category::class)->withoutGlobalScope('household'); }
    public function recurrences(): HasMany { return $this->hasMany(Recurrence::class)->withoutGlobalScope('household'); }
    public function debts(): HasMany { return $this->hasMany(Debt::class)->withoutGlobalScope('household'); }
    public function goals(): HasMany { return $this->hasMany(Goal::class)->withoutGlobalScope('household'); }
    public function assets(): HasMany { return $this->hasMany(Asset::class)->withoutGlobalScope('household'); }
}
