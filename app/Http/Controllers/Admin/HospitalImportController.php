<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HospitalImportAnalyzeRequest;
use App\Http\Requests\Admin\HospitalImportStoreRequest;
use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Services\AuditLogger;
use App\Services\HospitalDocxParser;
use App\Services\HospitalImportStaging;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Assistant « Ajouter un hôpital » (F2 complet) : upload d'un DOCX de
 * comptes rendus normaux, analyse via `HospitalDocxParser` (le même moteur
 * que le catalogue des 5 hôpitaux de départ), prévisualisation avec
 * correction avant import définitif. Aucun cas particulier codé en dur —
 * l'architecture supporte un nombre illimité d'hôpitaux.
 */
class HospitalImportController extends Controller
{
    public function __construct(
        private readonly HospitalDocxParser $parser,
        private readonly HospitalImportStaging $staging,
        private readonly AuditLogger $audit,
    ) {}

    public function create(): View
    {
        return view('admin.hospitals.import.create');
    }

    public function analyze(HospitalImportAnalyzeRequest $request): View|RedirectResponse
    {
        $template = $request->file('template');

        try {
            $parsed = $this->parser->parse($template->getRealPath());
        } catch (Throwable $e) {
            return back()->withInput()->withErrors([
                'template' => "Analyse du document impossible : {$e->getMessage()}",
            ]);
        }

        if ($parsed['exams'] === []) {
            return back()->withInput()->withErrors([
                'template' => 'Aucun examen détecté dans ce document. Vérifiez qu\'il s\'agit bien d\'un DOCX de comptes rendus normaux, un examen par page.',
            ]);
        }

        $token = $this->staging->stage(
            $request->input('name'),
            $request->input('radiologist_name'),
            $template,
            $parsed,
        );

        $staged = $this->staging->get($token);

        return view('admin.hospitals.import.preview', [
            'token' => $token,
            'name' => $staged['name'],
            'slug' => Str::slug($staged['name']),
            'radiologistName' => $staged['radiologist_name'],
            'primaryColor' => $staged['colors']['primary'] ?? '#1F3864',
            'exams' => $staged['exams'],
        ]);
    }

    public function store(HospitalImportStoreRequest $request): RedirectResponse
    {
        $staged = $this->staging->get($request->input('token'));

        if (! $staged) {
            return redirect()
                ->route('admin.hospitals.import.create')
                ->withErrors(['template' => "La prévisualisation a expiré, veuillez recommencer l'import."]);
        }

        $corrections = $request->input('exams', []);

        $exams = collect($staged['exams'])->values()->map(function (array $exam, int $index) use ($corrections) {
            $title = trim((string) ($corrections[$index]['title'] ?? $exam['title']));

            return [
                ...$exam,
                'title' => $title !== '' ? $title : $exam['title'],
                'requires_side' => ! empty($corrections[$index]['requires_side']),
            ];
        });

        $slug = $request->input('slug');
        $destination = "templates/{$slug}.docx";

        $hospital = DB::transaction(function () use ($request, $staged, $exams, $destination) {
            $hospital = Hospital::create([
                'name' => $request->input('name'),
                'slug' => $request->input('slug'),
                'colors' => ['primary' => $request->input('colors.primary') ?: ($staged['colors']['primary'] ?? '#1F3864')],
                'header_docx_path' => $destination,
                'radiologist_name' => $request->input('radiologist_name'),
                'active' => true,
            ]);

            foreach ($exams as $exam) {
                ExamTemplate::updateOrCreate(
                    ['hospital_id' => $hospital->id, 'title' => $exam['title']],
                    [
                        'heading' => $exam['heading'],
                        'modality' => $exam['modality'],
                        'requires_side' => $exam['requires_side'],
                        'indication' => $exam['indication'],
                        'technique' => $exam['technique'],
                        'results' => $exam['results'],
                        'conclusion' => $exam['conclusion'],
                        'active' => true,
                    ]
                );
            }

            return $hospital;
        });

        // Les templates institutionnels vivent sous storage/app/templates/
        // (hors des disques applicatifs, comme les 5 gabarits de départ —
        // lus directement par DocxReportGenerator via storage_path('app/...')),
        // pas dans le disque privé où l'upload a été mis en attente.
        $absoluteDestination = storage_path("app/{$destination}");
        File::ensureDirectoryExists(dirname($absoluteDestination));
        File::move(Storage::disk('local')->path($staged['docx_path']), $absoluteDestination);
        $this->staging->clear($request->input('token'));

        $this->audit->log('hopital_importe', $request->user(), $hospital, $request);

        return redirect()
            ->route('admin.hospitals.edit', $hospital)
            ->with('status', "Hôpital « {$hospital->name} » importé avec {$exams->count()} examen(s). Vérifiez le catalogue avant utilisation.");
    }
}
