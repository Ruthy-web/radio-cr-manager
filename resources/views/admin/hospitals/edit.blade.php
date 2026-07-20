@extends('layouts.admin')

@section('title', 'Modifier ' . $hospital->name . ' — Radio CR Manager')

@section('admin-content')
<h1 class="mb-6 text-xl font-semibold text-slate-900">Modifier « {{ $hospital->name }} »</h1>

<div class="max-w-xl rounded-xl border border-slate-200 bg-white p-6">
    <form method="POST" action="{{ route('admin.hospitals.update', $hospital) }}" class="space-y-6">
        @csrf
        @method('PUT')
        @include('admin.hospitals._form')

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.hospitals.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">Annuler</a>
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
