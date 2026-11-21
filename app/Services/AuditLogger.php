<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public static function log(string $action, ?Model $subject = null, ?string $description = null, array $properties = []): void
    {
        $label = $subject && method_exists($subject, 'auditLabel') ? $subject->auditLabel() : ($subject ? class_basename($subject).' #'.$subject->getKey() : null);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description ?? trim(ucfirst(str_replace('_', ' ', $action)).' '.$label),
            'properties' => $properties ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
