@extends('layouts.admin')

@section('title', 'Hôpitaux — Radio CR Manager')

@section('admin-content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-xl font-semibold text-slate-900">Hôpitaux</h1>
    <a href="{{ route('admin.hospitals.create') }}"
       class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
        Ajouter un hôpital
    </a>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Nom</th>
                <th class="px-4 py-3">Radiologue</th>
                <th class="px-4 py-3">Examens</th>
                <th class="px-4 py-3">Statut</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($hospitals as $hospital)
                <tr>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-3 w-3 rounded-full" style="background-color: {{ $hospital->primaryColor() }}"></span>
                            <span class="font-medium text-slate-900">{{ $hospital->name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $hospital->radiologist_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">
                        <a href="{{ route('admin.hospitals.exam-templates.index', $hospital) }}" class="text-slate-900 underline decoration-slate-300 hover:decoration-slate-900">
                            {{ $hospital->exam_templates_count }} examen(s)
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        @if ($hospital->trashed())
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Désactivé</span>
                        @else
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">Actif</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            @if ($hospital->trashed())
                                <form method="POST" action="{{ route('admin.hospitals.restore', $hospital) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs hover:bg-slate-100">
                                        Réactiver
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('admin.hospitals.edit', $hospital) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs hover:bg-slate-100">
                                    Modifier
                                </a>
                                <form method="POST" action="{{ route('admin.hospitals.destroy', $hospital) }}"
                                      onsubmit="return confirm('Désactiver cet hôpital ? Il restera consultable dans l\'historique.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-xs text-red-700 hover:bg-red-50">
                                        Désactiver
                                    </button>
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
