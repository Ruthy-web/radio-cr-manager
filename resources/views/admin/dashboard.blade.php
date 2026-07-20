@extends('layouts.app')

@section('title', 'Tableau de bord — Radio CR Manager')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Tableau de bord</h1>
            <p class="text-sm text-slate-500">
                Connecté en tant que {{ auth()->user()->name }} ({{ auth()->user()->role->label() }})
            </p>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">
                Se déconnecter
            </button>
        </form>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
        Le tableau de bord complet (hôpitaux, comptes rendus, sauvegardes, audit) sera construit aux étapes suivantes.
    </div>
</div>
@endsection
