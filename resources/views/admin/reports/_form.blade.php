@php
    $report = $report ?? null;
    $canEditMedical = auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Radiologue);
    $examsByHospital = $hospitals->mapWithKeys(fn ($h) => [$h->id => $h->examTemplates->map(fn ($e) => [
        'id' => $e->id,
        'title' => $e->title,
        'heading' => $e->heading,
        'indication' => $e->indication,
        'technique' => $e->technique,
        'results' => collect($e->results)->pluck('text')->implode("\n"),
        'conclusion' => $e->conclusion,
        'requires_side' => $e->requires_side,
    ])]);
    $initialResultsText = $report ? collect($report->content['results'] ?? [])->pluck('text')->implode("\n") : '';
@endphp

<div x-data="reportForm({
        examsByHospital: {{ $examsByHospital->toJson() }},
        hospitalId: {{ old('hospital_id', $report?->hospital_id) ?: 'null' }},
        examId: {{ old('exam_template_id', $report?->exam_template_id) ?: 'null' }},
        heading: @js(old('heading', $report?->content['heading'] ?? '')),
        indication: @js(old('indication', $report?->content['indication'] ?? '')),
        technique: @js(old('technique', $report?->content['technique'] ?? '')),
        resultsText: @js(old('results_text', $initialResultsText)),
        conclusion: @js(old('conclusion', $report?->content['conclusion'] ?? '')),
    })" class="space-y-6">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="hospital_id" class="mb-1 block text-sm font-medium text-slate-700">Hôpital</label>
            <select id="hospital_id" name="hospital_id" x-model.number="hospitalId" @change="onHospitalChange()" required
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                <option value="">— Choisir —</option>
                @foreach ($hospitals as $hospital)
                    <option value="{{ $hospital->id }}">{{ $hospital->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="exam_template_id" class="mb-1 block text-sm font-medium text-slate-700">Examen</label>
            <select id="exam_template_id" name="exam_template_id" x-model.number="examId" @change="onExamChange()"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                <option value="">— Aucun —</option>
                <template x-for="exam in currentExams()" :key="exam.id">
                    <option :value="exam.id" x-text="exam.title" :selected="exam.id === examId"></option>
                </template>
            </select>
        </div>
    </div>

    <div x-show="requiresSide()" x-cloak>
        <span class="mb-1 block text-sm font-medium text-slate-700">Côté</span>
        <label class="mr-4 inline-flex items-center gap-1 text-sm">
            <input type="radio" name="side" value="droit" {{ old('side', $report?->content['identity']['side'] ?? '') === 'droit' ? 'checked' : '' }}> Droit
        </label>
        <label class="inline-flex items-center gap-1 text-sm">
            <input type="radio" name="side" value="gauche" {{ old('side', $report?->content['identity']['side'] ?? '') === 'gauche' ? 'checked' : '' }}> Gauche
        </label>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label for="patient_name" class="mb-1 block text-sm font-medium text-slate-700">Nom du patient</label>
            <input id="patient_name" name="patient_name" type="text" required value="{{ old('patient_name', $report?->patient_name) }}"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
        </div>
        <div>
            <label for="patient_age" class="mb-1 block text-sm font-medium text-slate-700">Âge</label>
            <input id="patient_age" name="patient_age" type="text" value="{{ old('patient_age', $report?->patient_age) }}"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
        </div>
        <div>
            <label for="patient_sex" class="mb-1 block text-sm font-medium text-slate-700">Sexe</label>
            <select id="patient_sex" name="patient_sex"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                <option value="">— Non précisé —</option>
                <option value="M" @selected(old('patient_sex', $report?->patient_sex) === 'M')>Masculin</option>
                <option value="F" @selected(old('patient_sex', $report?->patient_sex) === 'F')>Féminin</option>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label for="file_number" class="mb-1 block text-sm font-medium text-slate-700">N° de dossier</label>
            <input id="file_number" name="file_number" type="text" value="{{ old('file_number', $report?->file_number) }}"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
        </div>
        <div>
            <label for="prescriber" class="mb-1 block text-sm font-medium text-slate-700">Prescripteur</label>
            <input id="prescriber" name="prescriber" type="text" value="{{ old('prescriber', $report?->prescriber) }}"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
        </div>
        <div>
            <label for="exam_date" class="mb-1 block text-sm font-medium text-slate-700">Date d'examen</label>
            <input id="exam_date" name="exam_date" type="date"
                   value="{{ old('exam_date', $report?->exam_date?->format('Y-m-d')) }}"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
        </div>
    </div>

    @if ($canEditMedical)
        <div class="border-t border-slate-200 pt-6">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Contenu médical</h2>

            <div class="space-y-4">
                <div>
                    <label for="heading" class="mb-1 block text-sm font-medium text-slate-700">Intitulé</label>
                    <input id="heading" name="heading" type="text" x-model="heading"
                           class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                </div>
                <div>
                    <label for="indication" class="mb-1 block text-sm font-medium text-slate-700">Indication</label>
                    <textarea id="indication" name="indication" rows="2" x-model="indication"
                              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"></textarea>
                </div>
                <div>
                    <label for="technique" class="mb-1 block text-sm font-medium text-slate-700">Technique</label>
                    <textarea id="technique" name="technique" rows="2" x-model="technique"
                              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"></textarea>
                </div>
                <div>
                    <label for="results_text" class="mb-1 block text-sm font-medium text-slate-700">Résultats (une constatation par ligne)</label>
                    <textarea id="results_text" name="results_text" rows="10" x-model="resultsText"
                              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"></textarea>
                </div>
                <div>
                    <label for="conclusion" class="mb-1 block text-sm font-medium text-slate-700">Conclusion</label>
                    <textarea id="conclusion" name="conclusion" rows="2" x-model="conclusion"
                              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"></textarea>
                </div>
            </div>
        </div>
    @else
        <p class="border-t border-slate-200 pt-6 text-sm text-slate-500">
            Le contenu médical sera rédigé par le radiologue à partir du modèle de l'examen sélectionné.
        </p>
    @endif
</div>
