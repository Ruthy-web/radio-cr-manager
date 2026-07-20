<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consultation du journal d'audit (F8) — réservée au rôle admin. Aucune
 * modification possible : le journal est en lecture seule, jamais purgé
 * manuellement depuis l'interface (traçabilité).
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::with('user')->orderByDesc('created_at')->orderByDesc('id');

        if ($action = $request->query('action')) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->paginate(30)->withQueryString();
        $users = User::orderBy('name')->get();

        return view('admin.audit.index', compact('logs', 'users'));
    }
}
