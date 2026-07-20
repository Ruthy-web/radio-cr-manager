@extends('layouts.admin')

@section('title', 'Modifier le compte rendu — Radio CR Manager')

@php($canEditMedical = auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Radiologue))
@php($statusColors = [
    'brouillon' => 'bg-slate-100 text-slate-600',
    'finalise' => 'bg-amber-100 text-amber-700',
    'signe' => 'bg-emerald-100 text-emerald-700',
])

@section('admin-content')
<a href="{{ route('admin.reports.index') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Comptes rendus</a>

<div class="mb-6 mt-1 flex items-center justify-between">
    <h1 class="text-xl font-semibold text-slate-900">
        Compte rendu — {{ $report->patient_name }}
    </h1>
    <div class="flex items-center gap-3">
        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $statusColors[$report->status->value] }}">
            {{ $report->status->label() }}
        </span>
        @if ($canEditMedical)
            @if ($report->status->value === 'brouillon')
                <form method="POST" action="{{ route('admin.reports.finalize', $report) }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-amber-300 px-3 py-1.5 text-xs text-amber-700 hover:bg-amber-50">Finaliser</button>
                </form>
            @elseif ($report->status->value === 'finalise')
                <form method="POST" action="{{ route('admin.reports.sign', $report) }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-emerald-300 px-3 py-1.5 text-xs text-emerald-700 hover:bg-emerald-50">Signer</button>
                </form>
            @endif
        @endif
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <form method="POST" action="{{ route('admin.reports.update', $report) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @include('admin.reports._form')

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-6">
                    <form method="POST" action="{{ route('admin.reports.destroy', $report) }}"
                          onsubmit="return confirm('Archiver ce compte rendu ?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md border border-red-200 px-4 py-2 text-sm text-red-700 hover:bg-red-50">Archiver</button>
                    </form>
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <div>
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Historique des versions</h2>
            <ul class="space-y-3 text-sm">
                @forelse ($report->versions as $version)
                    <li class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3 last:border-0">
                        <div>
                            <div class="text-slate-700">{{ $version->created_at->format('d/m/Y H:i') }}</div>
                            <div class="text-xs text-slate-500">{{ $version->author?->name ?? 'Système' }}</div>
                        </div>
                        @if ($canEditMedical)
                            <form method="POST" action="{{ route('admin.reports.versions.restore', [$report, $version]) }}"
                                  onsubmit="return confirm('Restaurer cette version ?');">
                                @csrf
                                <button type="submit" class="rounded-md border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100">Restaurer</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="text-slate-500">Aucune version enregistrée.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
