@php($hospital = $hospital ?? null)

<div class="space-y-4">
    <div>
        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Nom de l'hôpital</label>
        <input id="name" name="name" type="text" required value="{{ old('name', $hospital?->name) }}"
               class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
    </div>

    <div>
        <label for="slug" class="mb-1 block text-sm font-medium text-slate-700">
            Identifiant technique (slug)
        </label>
        <input id="slug" name="slug" type="text" required value="{{ old('slug', $hospital?->slug) }}"
               class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
        <p class="mt-1 text-xs text-slate-500">Lettres minuscules, chiffres et tirets uniquement.</p>
    </div>

    <div>
        <label for="radiologist_name" class="mb-1 block text-sm font-medium text-slate-700">Radiologue signataire</label>
        <input id="radiologist_name" name="radiologist_name" type="text" value="{{ old('radiologist_name', $hospital?->radiologist_name) }}"
               class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
    </div>

    <div>
        <label for="colors_primary" class="mb-1 block text-sm font-medium text-slate-700">Couleur principale des titres</label>
        <div class="flex items-center gap-3">
            <input id="colors_primary" name="colors[primary]" type="text" value="{{ old('colors.primary', $hospital?->primaryColor() ?? '#1F3864') }}"
                   class="w-40 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
            <span class="inline-block h-8 w-8 rounded-md border border-slate-200" style="background-color: {{ old('colors.primary', $hospital?->primaryColor() ?? '#1F3864') }}"></span>
        </div>
        <p class="mt-1 text-xs text-slate-500">Format hexadécimal, ex. #1F3864. Utilisée pour les titres de section des comptes rendus (R1).</p>
    </div>
</div>
