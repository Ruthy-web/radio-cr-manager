@extends('layouts.admin')

@section('title', 'Prévisualisation de l\'import — Radio CR Manager')

@section('admin-content')
<a href="{{ route('admin.hospitals.import.create') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Recommencer</a>

<h1 class="mb-2 mt-1 text-xl font-semibold text-slate-900">Prévisualisation — {{ count($exams) }} examen(s) détecté(s)</h1>
<p class="mb-6 text-sm text-slate-500">
    Relisez et corrigez si besoin avant de valider. Les autres champs de chaque examen (technique, résultats,
    conclusion) restent modifiables individuellement après l'import, depuis la fiche de l'hôpital.
</p>

<form method="POST" action="{{ route('admin.hospitals.import.store') }}" class="space-y-6">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Hôpital</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Nom</label>
                <input id="name" name="name" type="text" required value="{{ old('name', $name) }}"
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
            </div>
            <div>
                <label for="slug" class="mb-1 block text-sm font-medium text-slate-700">Identifiant (slug)</label>
                <input id="slug" name="slug" type="text" required value="{{ old('slug', $slug) }}"
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
            </div>
            <div>
                <label for="radiologist_name" class="mb-1 block text-sm font-medium text-slate-700">Radiologue signataire</label>
                <input id="radiologist_name" name="radiologist_name" type="text" value="{{ old('radiologist_name', $radiologistName) }}"
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
            </div>
            <div>
                <label for="colors_primary" class="mb-1 block text-sm font-medium text-slate-700">Couleur principale détectée</label>
                <div class="flex items-center gap-3">
                    <input id="colors_primary" name="colors[primary]" type="text" value="{{ old('colors.primary', $primaryColor) }}"
                           class="w-40 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <span class="inline-block h-8 w-8 rounded-md border border-slate-200" style="background-color: {{ $primaryColor }}"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Examens détectés</h2>

        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="max-h-[32rem] overflow-y-auto overflow-x-auto">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Titre</th>
                        <th class="px-3 py-2">Latéralité</th>
                        <th class="px-3 py-2">Technique</th>
                        <th class="px-3 py-2">Résultats</th>
                        <th class="px-3 py-2">Conclusion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($exams as $index => $exam)
                        <tr>
                            <td class="px-3 py-2">
                                <input type="text" name="exams[{{ $index }}][title]" value="{{ $exam['title'] }}"
                                       class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <input type="checkbox" name="exams[{{ $index }}][requires_side]" value="1"
                                       @checked($exam['requires_side'] ?? false)>
                            </td>
                            <td class="px-3 py-2 text-slate-600">{{ \Illuminate\Support\Str::limit($exam['technique'] ?? '', 60) }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ count($exam['results'] ?? []) }} ligne(s)</td>
                            <td class="px-3 py-2 text-slate-600">{{ \Illuminate\Support\Str::limit($exam['conclusion'] ?? '', 60) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.hospitals.import.create') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">Annuler</a>
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Valider l'import
        </button>
    </div>
</form>
@endsection
