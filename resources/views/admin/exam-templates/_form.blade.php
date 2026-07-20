@php($examTemplate = $examTemplate ?? null)
@php($resultsText = old('results_text', $examTemplate ? collect($examTemplate->results)->pluck('text')->implode("\n") : ''))

<div class="space-y-4">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="title" class="mb-1 block text-sm font-medium text-slate-700">Titre (catalogue)</label>
            <input id="title" name="title" type="text" required value="{{ old('title', $examTemplate?->title) }}"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
        </div>
        <div>
            <label for="modality" class="mb-1 block text-sm font-medium text-slate-700">Modalité</label>
            <input id="modality" name="modality" type="text" value="{{ old('modality', $examTemplate?->modality) }}"
                   placeholder="radiographie, echographie, ..."
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
        </div>
    </div>

    <div>
        <label for="heading" class="mb-1 block text-sm font-medium text-slate-700">Intitulé imprimé sur le compte rendu</label>
        <input id="heading" name="heading" type="text" required value="{{ old('heading', $examTemplate?->heading) }}"
               class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
    </div>

    <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="requires_side" value="0">
        <input type="checkbox" name="requires_side" value="1"
               @checked(old('requires_side', $examTemplate?->requires_side))
               class="rounded border-slate-300">
        Nécessite de préciser le côté (Droit / Gauche)
    </label>

    <div>
        <label for="indication" class="mb-1 block text-sm font-medium text-slate-700">Indication</label>
        <textarea id="indication" name="indication" rows="2"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">{{ old('indication', $examTemplate?->indication) }}</textarea>
    </div>

    <div>
        <label for="technique" class="mb-1 block text-sm font-medium text-slate-700">Technique</label>
        <textarea id="technique" name="technique" rows="2"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">{{ old('technique', $examTemplate?->technique) }}</textarea>
    </div>

    <div>
        <label for="results_text" class="mb-1 block text-sm font-medium text-slate-700">Résultats normaux (une constatation par ligne)</label>
        <textarea id="results_text" name="results_text" rows="10"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">{{ $resultsText }}</textarea>
        <p class="mt-1 text-xs text-slate-500">
            Chaque ligne devient une constatation du compte rendu. La dictée vocale remplacera automatiquement la
            ligne correspondante en cas d'anomalie (F5).
        </p>
    </div>

    <div>
        <label for="conclusion" class="mb-1 block text-sm font-medium text-slate-700">Conclusion</label>
        <textarea id="conclusion" name="conclusion" rows="2"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">{{ old('conclusion', $examTemplate?->conclusion) }}</textarea>
    </div>
</div>
