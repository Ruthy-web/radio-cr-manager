@php($user = $user ?? null)

<div class="space-y-4">
    <div>
        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Nom</label>
        <input id="name" name="name" type="text" required value="{{ old('name', $user?->name) }}"
               class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
    </div>

    <div>
        <label for="email" class="mb-1 block text-sm font-medium text-slate-700">E-mail</label>
        <input id="email" name="email" type="email" required value="{{ old('email', $user?->email) }}"
               class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
    </div>

    <div>
        <label for="role" class="mb-1 block text-sm font-medium text-slate-700">Rôle</label>
        <select id="role" name="role" required
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
            @foreach (\App\Enums\UserRole::cases() as $role)
                <option value="{{ $role->value }}" @selected(old('role', $user?->role?->value) === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="password" class="mb-1 block text-sm font-medium text-slate-700">
            Mot de passe {{ $user ? '(laisser vide pour conserver l’actuel)' : '' }}
        </label>
        <input id="password" name="password" type="password" autocomplete="new-password" {{ $user ? '' : 'required' }}
               class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
        <p class="mt-1 text-xs text-slate-500">Au moins 10 caractères, majuscule, minuscule, chiffre et symbole.</p>
    </div>

    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="two_factor_enabled" value="0">
        <input type="checkbox" name="two_factor_enabled" value="1" @checked(old('two_factor_enabled', $user?->two_factor_enabled))>
        Authentification à deux facteurs par e-mail
    </label>
</div>
