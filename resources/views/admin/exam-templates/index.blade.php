@extends('layouts.admin')

@section('title', 'Examens — ' . $hospital->name)

@section('admin-content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <a href="{{ route('admin.hospitals.index') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Hôpitaux</a>
        <h1 class="text-xl font-semibold text-slate-900">Catalogue d'examens — {{ $hospital->name }}</h1>
    </div>
    <a href="{{ route('admin.hospitals.exam-templates.create', $hospital) }}"
       class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
        Ajouter un examen
    </a>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Titre</th>
                <th class="px-4 py-3">Modalité</th>
                <th class="px-4 py-3">Côté</th>
                <th class="px-4 py-3">Statut</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($examTemplates as $exam)
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $exam->title }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $exam->modality ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $exam->requires_side ? 'D / G' : '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($exam->trashed())
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Désactivé</span>
                        @else
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">Actif</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            @if ($exam->trashed())
                                <form method="POST" action="{{ route('admin.hospitals.exam-templates.restore', [$hospital, $exam]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs hover:bg-slate-100">Réactiver</button>
                                </form>
                            @else
                                <a href="{{ route('admin.hospitals.exam-templates.edit', [$hospital, $exam]) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs hover:bg-slate-100">Modifier</a>
                                <form method="POST" action="{{ route('admin.hospitals.exam-templates.destroy', [$hospital, $exam]) }}"
                                      onsubmit="return confirm('Désactiver cet examen ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-xs text-red-700 hover:bg-red-50">Désactiver</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
