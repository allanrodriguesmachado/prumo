<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['household_id', 'user_id', 'event', 'auditable_type', 'auditable_id', 'changes', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Registra um evento de segurança ou de dados. Nunca grava senhas/segredos. */
    public static function record(string $event, ?Model $subject = null, array $changes = [], ?User $user = null): void
    {
        $user ??= Auth::user();
        $req = app()->runningInConsole() ? null : request();
        static::create([
            'household_id' => $subject?->getAttribute('household_id') ?? $user?->current_household_id,
            'user_id' => $user?->id,
            'event' => $event,
            'auditable_type' => $subject ? class_basename($subject) : null,
            'auditable_id' => $subject?->getKey(),
            'changes' => $changes ?: null,
            'ip_address' => $req?->ip(),
            'user_agent' => $req ? mb_substr((string) $req->userAgent(), 0, 255) : null,
        ]);
    }
}
