@extends('layouts.admin')

@section('title', 'Tableau de bord — Radio CR Manager')

@php($isAdmin = auth()->user()->hasRole(\App\Enums\UserRole::Admin))

@section('admin-content')
<h1 class="mb-1 text-xl font-semibold text-slate-900">Tableau de bord</h1>
<p class="mb-6 text-sm text-slate-500">
    Connecté en tant que {{ auth()->user()->name }} ({{ auth()->user()->role->label() }})
</p>

<div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <span class="text-xs uppercase tracking-wide text-slate-500">Comptes rendus</span>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $stats['reports_total'] }}</p>
        <span class="text-xs text-slate-500">{{ $stats['reports_today'] }} aujourd'hui</span>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <span class="text-xs uppercase tracking-wide text-slate-500">Brouillons</span>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $stats['reports_by_status']['brouillon'] ?? 0 }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <span class="text-xs uppercase tracking-wide text-slate-500">Finalisés</span>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $stats['reports_by_status']['finalise'] ?? 0 }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <span class="text-xs uppercase tracking-wide text-slate-500">Signés</span>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $stats['reports_by_status']['signe'] ?? 0 }}</p>
    </div>
</div>

<div class="flex flex-wrap gap-3 mb-6">
    <a href="{{ route('admin.reports.index') }}"
       class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
        Comptes rendus
    </a>
    @if ($isAdmin)
        <a href="{{ route('admin.hospitals.index') }}"
           class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
            Hôpitaux et catalogues d'examens ({{ $stats['hospitals_active'] }} actifs)
        </a>
        <a href="{{ route('admin.audit.index') }}"
           class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
            Journal d'audit
        </a>
        <a href="{{ route('admin.settings.api-credentials.edit') }}"
           class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
            Clés API
        </a>
    @endif
</div>

@if ($isAdmin)
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Système</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-slate-500">Utilisateurs actifs</dt>
                <dd class="text-slate-900">{{ $stats['users_active'] }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Dernière sauvegarde</dt>
                <dd class="text-slate-900">{{ $lastBackup ?? 'Aucune sauvegarde effectuée' }}</dd>
            </div>
        </dl>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Activité récente</h2>
            <a href="{{ route('admin.audit.index') }}" class="text-xs text-slate-500 underline decoration-slate-300 hover:decoration-slate-900">Tout voir</a>
        </div>
        <ul class="space-y-2 text-sm">
            @forelse ($recentAudits as $log)
                <li class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2 last:border-0">
                    <span class="text-slate-700">{{ $log->action }}</span>
                    <span class="text-xs text-slate-500">{{ $log->user?->name ?? 'Système' }} · {{ $log->created_at->format('d/m H:i') }}</span>
                </li>
            @empty
                <li class="text-slate-500">Aucune activité journalisée.</li>
            @endforelse
        </ul>
    </div>
</div>
@endif
@endsection
