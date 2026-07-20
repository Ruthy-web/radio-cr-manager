<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Hospital;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'reports_total' => Report::count(),
            'reports_today' => Report::whereDate('created_at', today())->count(),
            'reports_by_status' => Report::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'hospitals_active' => Hospital::where('active', true)->count(),
            'users_active' => User::where('active', true)->count(),
        ];

        $recentAudits = AuditLog::with('user')->latest('id')->take(10)->get();

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentAudits' => $recentAudits,
            'lastBackup' => $this->lastBackupAt(),
        ]);
    }

    private function lastBackupAt(): ?string
    {
        $disk = Storage::disk(config('radiology.backup_disk'));
        $files = collect($disk->files('backups'))
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->sortByDesc(fn (string $path) => $path);

        $latest = $files->first();

        return $latest ? date('Y-m-d H:i:s', $disk->lastModified($latest)) : null;
    }
}
