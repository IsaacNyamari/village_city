<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Support\Arr;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => AuditLogger::log('created', $model));

        static::updated(function ($model) {
            $changes = Arr::except($model->getChanges(), ['updated_at', 'password', 'remember_token', 'last_login_at']);

            if ($changes !== []) {
                AuditLogger::log(array_key_exists('status', $changes) ? 'status_changed' : 'updated', $model, null, ['changes' => $changes]);
            }
        });

        static::deleted(fn ($model) => AuditLogger::log('deleted', $model));
    }

    public function auditLabel(): string
    {
        $name = $this->reference ?? $this->number ?? $this->code ?? $this->name ?? $this->business_name ?? $this->registration ?? $this->getKey();

        return class_basename($this).' '.$name;
    }
}
