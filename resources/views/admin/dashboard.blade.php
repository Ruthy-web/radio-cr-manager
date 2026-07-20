@extends('layouts.admin')

@section('title', 'Tableau de bord — Radio CR Manager')

@section('admin-content')
<h1 class="mb-1 text-xl font-semibold text-slate-900">Tableau de bord</h1>
<p class="mb-6 text-sm text-slate-500">
    Connecté en tant que {{ auth()->user()->name }} ({{ auth()->user()->role->label() }})
</p>

<div class="flex flex-wrap gap-3">
    <a href="{{ route('admin.reports.index') }}"
       class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
        Comptes rendus
    </a>
    @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin))
        <a href="{{ route('admin.hospitals.index') }}"
           class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
            Hôpitaux et catalogues d'examens
        </a>
    @endif
</div>
@endsection
