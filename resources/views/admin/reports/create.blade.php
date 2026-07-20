@extends('layouts.admin')

@section('title', 'Nouveau compte rendu — Radio CR Manager')

@section('admin-content')
<a href="{{ route('admin.reports.index') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Comptes rendus</a>
<h1 class="mb-6 mt-1 text-xl font-semibold text-slate-900">Nouveau compte rendu</h1>

<div class="max-w-3xl rounded-xl border border-slate-200 bg-white p-6">
    <form method="POST" action="{{ route('admin.reports.store') }}" class="space-y-6">
        @csrf
        @include('admin.reports._form')

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-6">
            <a href="{{ route('admin.reports.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">Annuler</a>
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Créer</button>
        </div>
    </form>
</div>
@endsection
