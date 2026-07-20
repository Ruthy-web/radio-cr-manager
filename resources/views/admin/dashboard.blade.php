@extends('layouts.admin')

@section('title', 'Tableau de bord — Radio CR Manager')

@section('admin-content')
<h1 class="mb-1 text-xl font-semibold text-slate-900">Tableau de bord</h1>
<p class="mb-6 text-sm text-slate-500">
    Connecté en tant que {{ auth()->user()->name }} ({{ auth()->user()->role->label() }})
</p>

@if (auth()->user()->hasRole(\App\Enums\UserRole::Admin))
    <a href="{{ route('admin.hospitals.index') }}"
       class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
        Gérer les hôpitaux et catalogues d'examens
    </a>
@else
    <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
        Les comptes rendus (F3) seront construits à l'étape suivante.
    </div>
@endif
@endsection
