@extends('layouts.admin')

@section('title', 'Clés API — Radio CR Manager')

@section('admin-content')
<h1 class="mb-2 text-xl font-semibold text-slate-900">Clés API</h1>
<p class="mb-6 text-sm text-slate-500">
    Ces clés alimentent les fonctionnalités IA de la PWA (transcription vocale, raffinage et rédaction
    assistée). Elles sont chiffrées en base et ne quittent jamais le serveur.
</p>

<div class="max-w-xl rounded-xl border border-slate-200 bg-white p-6">
    <form method="POST" action="{{ route('admin.settings.api-credentials.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        @foreach ($providers as $provider => $label)
            <div>
                <label for="{{ $provider }}_api_key" class="mb-1 block text-sm font-medium text-slate-700">
                    {{ $label }}
                </label>
                <input id="{{ $provider }}_api_key" name="{{ $provider }}_api_key" type="password"
                       placeholder="{{ $statuses[$provider] ? '•••••••••••••••• (clé déjà configurée)' : 'Aucune clé configurée' }}"
                       autocomplete="off"
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                <p class="mt-1 text-xs {{ $statuses[$provider] ? 'text-emerald-600' : 'text-slate-500' }}">
                    {{ $statuses[$provider] ? 'Configurée.' : 'Non configurée.' }}
                    Laisser vide pour conserver la clé actuelle.
                </p>
            </div>
        @endforeach

        <div class="flex justify-end border-t border-slate-200 pt-6">
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Enregistrer
            </button>
        </div>
    </form>
</div>
@endsection
