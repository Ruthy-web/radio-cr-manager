@extends('layouts.admin')

@section('title', 'Importer un hôpital — Radio CR Manager')

@section('admin-content')
<a href="{{ route('admin.hospitals.index') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Hôpitaux</a>

<h1 class="mb-2 mt-1 text-xl font-semibold text-slate-900">Assistant « Ajouter un hôpital »</h1>
<p class="mb-6 text-sm text-slate-500">
    Importez le catalogue d'examens d'un nouvel hôpital directement depuis son DOCX de comptes rendus
    normaux (un examen par page). Le document est analysé automatiquement, puis vous pourrez relire et
    corriger le résultat avant validation.
</p>

<div class="max-w-xl rounded-xl border border-slate-200 bg-white p-6">
    <form method="POST" action="{{ route('admin.hospitals.import.analyze') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div>
            <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Nom de l'hôpital</label>
            <input id="name" name="name" type="text" required value="{{ old('name') }}"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
        </div>

        <div>
            <label for="radiologist_name" class="mb-1 block text-sm font-medium text-slate-700">Radiologue signataire</label>
            <input id="radiologist_name" name="radiologist_name" type="text" value="{{ old('radiologist_name') }}"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
        </div>

        <div>
            <label for="template" class="mb-1 block text-sm font-medium text-slate-700">DOCX de comptes rendus normaux</label>
            <input id="template" name="template" type="file" required accept=".docx"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
            <p class="mt-1 text-xs text-slate-500">
                Format .docx, un examen par page (entête, technique, résultats, conclusion).
            </p>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.hospitals.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">Annuler</a>
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Analyser</button>
        </div>
    </form>
</div>
@endsection
