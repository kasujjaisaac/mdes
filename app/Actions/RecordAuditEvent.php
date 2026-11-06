<?php

namespace App\Actions;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RecordAuditEvent
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function handle(Request $request, string $action, Model $model, ?array $oldValues = null, ?array $newValues = null, ?string $reason = null): void
    {
        AuditEvent::query()->create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => $request->ip(),
        ]);
    }
}
