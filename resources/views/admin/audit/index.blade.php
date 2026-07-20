@extends('layouts.admin')

@section('title', "Journal d'audit — Radio CR Manager")

@section('admin-content')
<a href="{{ route('admin.dashboard') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Tableau de bord</a>

<h1 class="mb-6 mt-1 text-xl font-semibold text-slate-900">Journal d'audit</h1>

<form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-700">Action</label>
        <input type="text" name="action" value="{{ request('action') }}" placeholder="ex. connexion, compte_rendu"
               class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-700">Utilisateur</label>
        <select name="user_id" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
            <option value="">Tous</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-700">Du</label>
        <input type="date" name="from" value="{{ request('from') }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-700">Au</label>
        <input type="date" name="to" value="{{ request('to') }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
    </div>
    <button type="submit" class="rounded-md bg-slate-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Filtrer</button>
    <a href="{{ route('admin.audit.index') }}" class="rounded-md border border-slate-300 px-4 py-1.5 text-sm hover:bg-slate-100">Réinitialiser</a>
</form>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Utilisateur</th>
                <th class="px-4 py-3">Action</th>
                <th class="px-4 py-3">Sujet</th>
                <th class="px-4 py-3">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($logs as $log)
                <tr>
                    <td class="px-4 py-3 text-slate-600">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                    <td class="px-4 py-3 text-slate-900">{{ $log->user?->name ?? 'Système' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $log->action }}</td>
                    <td class="px-4 py-3 text-slate-500">
                        @if ($log->subject_type)
                            {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ $log->ip ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-slate-500">Aucune entrée pour ces critères.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $logs->links() }}
</div>
@endsection
