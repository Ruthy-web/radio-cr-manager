@extends('layouts.admin')

@section('title', 'Utilisateurs — Radio CR Manager')

@section('admin-content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-xl font-semibold text-slate-900">Utilisateurs</h1>
    <a href="{{ route('admin.users.create') }}"
       class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
        Ajouter un utilisateur
    </a>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Nom</th>
                <th class="px-4 py-3">E-mail</th>
                <th class="px-4 py-3">Rôle</th>
                <th class="px-4 py-3">2FA</th>
                <th class="px-4 py-3">Statut</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($users as $user)
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $user->name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $user->role->label() }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $user->two_factor_enabled ? 'Activée' : '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($user->trashed())
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Désactivé</span>
                        @else
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">Actif</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            @if ($user->trashed())
                                <form method="POST" action="{{ route('admin.users.restore', $user) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs hover:bg-slate-100">
                                        Réactiver
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('admin.users.edit', $user) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs hover:bg-slate-100">
                                    Modifier
                                </a>
                                @if ($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                          onsubmit="return confirm('Désactiver ce compte ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-xs text-red-700 hover:bg-red-50">
                                            Désactiver
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
