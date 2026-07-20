<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRequest;
use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Models\Report;
use App\Models\ReportVersion;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Historique groupé par journée, avec recherche par nom / date / hôpital / examen (F3).
     * Le nom du patient étant chiffré au repos (R3), le filtre nominatif est
     * appliqué en mémoire après déchiffrement plutôt qu'en SQL.
     */
    public function index(Request $request): View
    {
        $query = Report::with(['hospital', 'examTemplate', 'user'])
            ->orderByDesc('exam_date')
            ->orderByDesc('id');

        if ($hospitalId = $request->query('hospital_id')) {
            $query->where('hospital_id', $hospitalId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('exam_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('exam_date', '<=', $to);
        }

        $reports = $query->get();

        if ($name = trim((string) $request->query('patient_name'))) {
            $reports = $reports->filter(
                fn (Report $report) => $report->patient_name && str_contains(
                    mb_strtolower($report->patient_name),
                    mb_strtolower($name)
                )
            );
        }

        $groups = $reports->groupBy(
            fn (Report $report) => ($report->exam_date ?? $report->created_at)->format('Y-m-d')
        );

        $hospitals = Hospital::orderBy('name')->get();

        return view('admin.reports.index', compact('groups', 'hospitals'));
    }

    public function create(): View
    {
        $hospitals = Hospital::with(['examTemplates' => fn ($q) => $q->orderBy('title')])
            ->orderBy('name')
            ->get();

        return view('admin.reports.create', compact('hospitals'));
    }

    public function store(ReportRequest $request): RedirectResponse
    {
        $report = Report::create([
            ...$request->safe()->only([
                'hospital_id', 'exam_template_id', 'patient_name', 'patient_age',
                'patient_sex', 'file_number', 'prescriber', 'exam_date',
            ]),
            'user_id' => $request->user()->id,
            'content' => $this->buildContent($request),
        ]);

        $this->audit->log('compte_rendu_cree', $request->user(), $report, $request);

        return redirect()
            ->route('admin.reports.edit', $report)
            ->with('status', 'Compte rendu créé.');
    }

    public function edit(Report $report): View
    {
        $report->load(['hospital', 'examTemplate', 'versions.author']);
        $hospitals = Hospital::with(['examTemplates' => fn ($q) => $q->orderBy('title')])
            ->orderBy('name')
            ->get();

        return view('admin.reports.edit', compact('report', 'hospitals'));
    }

    public function update(ReportRequest $request, Report $report): RedirectResponse
    {
        $data = $request->safe()->only([
            'hospital_id', 'exam_template_id', 'patient_name', 'patient_age',
            'patient_sex', 'file_number', 'prescriber', 'exam_date',
        ]);

        if ($request->canEditMedicalContent()) {
            $data['content'] = $this->buildContent($request);
        }

        $report->update($data);
        $this->audit->log('compte_rendu_modifie', $request->user(), $report, $request);

        return redirect()
            ->route('admin.reports.edit', $report)
            ->with('status', 'Compte rendu mis à jour.');
    }

    /**
     * Archivage (soft delete) — jamais de suppression physique (règle F3/R3).
     */
    public function destroy(Report $report): RedirectResponse
    {
        $report->delete();
        $this->audit->log('compte_rendu_archive', request()->user(), $report, request());

        return redirect()->route('admin.reports.index')->with('status', 'Compte rendu archivé.');
    }

    public function finalize(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeMedical($request);
        $report->finalize();
        $this->audit->log('compte_rendu_finalise', $request->user(), $report, $request);

        return back()->with('status', 'Compte rendu finalisé.');
    }

    public function sign(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeMedical($request);
        $report->sign();
        $this->audit->log('compte_rendu_signe', $request->user(), $report, $request);

        return back()->with('status', 'Compte rendu signé.');
    }

    public function restoreVersion(Request $request, Report $report, ReportVersion $version): RedirectResponse
    {
        $this->authorizeMedical($request);
        abort_unless($version->report_id === $report->id, 404);

        $report->restoreVersion($version);
        $this->audit->log('compte_rendu_version_restauree', $request->user(), $report, $request);

        return redirect()
            ->route('admin.reports.edit', $report)
            ->with('status', 'Version restaurée.');
    }

    private function authorizeMedical(Request $request): void
    {
        abort_unless(
            $request->user()->hasRole(UserRole::Admin, UserRole::Radiologue),
            403,
            'Seul un radiologue peut valider le contenu médical.'
        );
    }

    private function buildContent(ReportRequest $request): array
    {
        if (! $request->canEditMedicalContent()) {
            // La secrétaire ne rédige pas le contenu médical : on reprend le
            // template de l'examen sélectionné tel quel (F1).
            $exam = $request->input('exam_template_id')
                ? ExamTemplate::find($request->input('exam_template_id'))
                : null;

            return [
                'heading' => $exam?->heading,
                'identity' => ['side' => $request->input('side')],
                'indication' => $exam?->indication,
                'technique' => $exam?->technique,
                'results' => $exam?->results ?? [],
                'conclusion' => $exam?->conclusion,
            ];
        }

        return [
            'heading' => $request->input('heading'),
            'identity' => ['side' => $request->input('side')],
            'indication' => $request->input('indication'),
            'technique' => $request->input('technique'),
            'results' => $request->resultsAsArray(),
            'conclusion' => $request->input('conclusion'),
        ];
    }
}
