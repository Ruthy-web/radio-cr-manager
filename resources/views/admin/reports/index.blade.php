@extends('layouts.admin')

@section('title', 'Comptes rendus — Radio CR Manager')

@php($statusColors = [
    'brouillon' => 'bg-slate-100 text-slate-600',
    'finalise' => 'bg-amber-100 text-amber-700',
    'signe' => 'bg-emerald-100 text-emerald-700',
])

@section('admin-content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-xl font-semibold text-slate-900">Comptes rendus</h1>
    <a href="{{ route('admin.reports.create') }}"
       class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
        Nouveau compte rendu
    </a>
</div>

<form method="GET" action="{{ route('admin.reports.index') }}"
      class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-5">
    <input type="text" name="patient_name" value="{{ request('patient_name') }}" placeholder="Nom du patient"
           class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
    <select name="hospital_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
        <option value="">Tous les hôpitaux</option>
        @foreach ($hospitals as $hospital)
            <option value="{{ $hospital->id }}" @selected(request('hospital_id') == $hospital->id)>{{ $hospital->name }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}"
           class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
    <input type="date" name="to" value="{{ request('to') }}"
           class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
    <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">Filtrer</button>
</form>

@forelse ($groups as $day => $dayReports)
    <div class="mb-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
            {{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('l d F Y') }}
            <span class="font-normal text-slate-400">({{ $dayReports->count() }})</span>
        </h2>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Patient</th>
                        <th class="px-4 py-3">Hôpital</th>
                        <th class="px-4 py-3">Examen</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3">Auteur</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($dayReports as $report)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $report->patient_name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $report->hospital->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $report->examTemplate?->title ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $statusColors[$report->status->value] }}">
                                    {{ $report->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $report->user->name }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.reports.edit', $report) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs hover:bg-slate-100">
                                    Ouvrir
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
        Aucun compte rendu pour ces critères.
    </div>
@endforelse
@endsection
