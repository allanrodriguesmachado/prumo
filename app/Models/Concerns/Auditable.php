<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/** Registra criação, alteração e exclusão de registros financeiros. */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => AuditLog::record('created', $m, $m->auditPayload($m->getAttributes())));
        static::updated(function ($m) {
            $changes = $m->getChanges();
            unset($changes['updated_at']);
            if ($changes) {
                $old = array_intersect_key($m->getOriginal(), $changes);
                AuditLog::record('updated', $m, ['old' => $m->auditPayload($old), 'new' => $m->auditPayload($changes)]);
            }
        });
        static::deleted(fn ($m) => AuditLog::record('deleted', $m, $m->auditPayload($m->getAttributes())));
    }

    protected function auditPayload(array $attrs): array
    {
        // campos criptografados/sensíveis nunca vão para o log
        return array_diff_key($attrs, array_flip(['notes', 'created_at', 'updated_at', 'password', 'remember_token']));
    }
}
