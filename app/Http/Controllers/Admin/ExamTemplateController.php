<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExamTemplateRequest;
use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExamTemplateController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Hospital $hospital): View
    {
        $examTemplates = $hospital->examTemplates()
            ->withTrashed()
            ->orderBy('title')
            ->get();

        return view('admin.exam-templates.index', compact('hospital', 'examTemplates'));
    }

    public function create(Hospital $hospital): View
    {
        return view('admin.exam-templates.create', compact('hospital'));
    }

    public function store(ExamTemplateRequest $request, Hospital $hospital): RedirectResponse
    {
        $examTemplate = $hospital->examTemplates()->create([
            ...$request->safe()->except(['results_text']),
            'results' => $request->resultsAsArray(),
        ]);

        $this->audit->log('examen_cree', $request->user(), $examTemplate, $request);

        return redirect()
            ->route('admin.hospitals.exam-templates.index', $hospital)
            ->with('status', "Examen « {$examTemplate->title} » créé.");
    }

    public function edit(Hospital $hospital, ExamTemplate $examTemplate): View
    {
        return view('admin.exam-templates.edit', compact('hospital', 'examTemplate'));
    }

    public function update(ExamTemplateRequest $request, Hospital $hospital, ExamTemplate $examTemplate): RedirectResponse
    {
        $examTemplate->update([
            ...$request->safe()->except(['results_text']),
            'results' => $request->resultsAsArray(),
        ]);

        $this->audit->log('examen_modifie', $request->user(), $examTemplate, $request);

        return redirect()
            ->route('admin.hospitals.exam-templates.index', $hospital)
            ->with('status', "Examen « {$examTemplate->title} » mis à jour.");
    }

    /**
     * Désactivation (soft delete) — jamais de suppression physique (F2).
     */
    public function destroy(Hospital $hospital, ExamTemplate $examTemplate): RedirectResponse
    {
        $examTemplate->update(['active' => false]);
        $examTemplate->delete();
        $this->audit->log('examen_desactive', request()->user(), $examTemplate, request());

        return redirect()
            ->route('admin.hospitals.exam-templates.index', $hospital)
            ->with('status', "Examen « {$examTemplate->title} » désactivé.");
    }

    public function restore(Hospital $hospital, int $examTemplate): RedirectResponse
    {
        $examTemplateModel = ExamTemplate::withTrashed()->findOrFail($examTemplate);
        $examTemplateModel->restore();
        $examTemplateModel->update(['active' => true]);
        $this->audit->log('examen_reactive', request()->user(), $examTemplateModel, request());

        return redirect()
            ->route('admin.hospitals.exam-templates.index', $hospital)
            ->with('status', "Examen « {$examTemplateModel->title} » réactivé.");
    }
}
