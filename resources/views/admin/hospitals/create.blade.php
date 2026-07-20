@extends('layouts.admin')

@section('title', 'Ajouter un hôpital — Radio CR Manager')

@section('admin-content')
<h1 class="mb-6 text-xl font-semibold text-slate-900">Ajouter un hôpital</h1>

<div class="max-w-xl rounded-xl border border-slate-200 bg-white p-6">
    <form method="POST" action="{{ route('admin.hospitals.store') }}" class="space-y-6">
        @csrf
        @include('admin.hospitals._form')

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.hospitals.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">Annuler</a>
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Créer</button>
        </div>
    </form>

    <p class="mt-6 text-xs text-slate-500">
        Pour importer automatiquement le catalogue d'examens à partir d'un DOCX de comptes rendus normaux,
        utilisez plutôt <a href="{{ route('admin.hospitals.import.create') }}" class="underline decoration-slate-300 hover:decoration-slate-900">l'assistant d'import</a>.
    </p>
</div>
@endsection
