<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Journalise les actions sensibles (F8/F9). Ne doit jamais recevoir de
 * donnée médicale en clair : seuls des identifiants et métadonnées techniques
 * sont enregistrés (R3).
 */
class AuditLogger
{
    public function log(string $action, ?User $user = null, ?Model $subject = null, ?Request $request = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
