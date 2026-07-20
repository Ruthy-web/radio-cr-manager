<?php

use App\Jobs\GenerateReportDocumentJob;
use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Models\Report;
use App\Services\DocxReportGenerator;
use App\Services\DocxToPdfService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $hospital = Hospital::factory()->create(['header_docx_path' => 'templates/nkoulou.docx']);
    $exam = ExamTemplate::factory()->for($hospital)->create([
        'title' => 'Radiographie du Thorax',
        'heading' => 'Compte Rendu de Radiographie du Thorax',
    ]);

    $this->report = Report::factory()->for($hospital)->for($exam, 'examTemplate')->create();
});

it('génère les pièces jointes DOCX et PDF du compte rendu', function () {
    (new GenerateReportDocumentJob($this->report->id))->handle(
        app(DocxReportGenerator::class),
        app(DocxToPdfService::class),
    );

    $attachments = $this->report->fresh()->attachments;

    expect($attachments)->toHaveCount(2);

    $docx = $attachments->firstWhere('mime', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $pdf = $attachments->firstWhere('mime', 'application/pdf');

    expect($docx)->not->toBeNull()
        ->and($pdf)->not->toBeNull()
        ->and(Storage::disk('local')->exists($docx->path))->toBeTrue()
        ->and(Storage::disk('local')->exists($pdf->path))->toBeTrue()
        ->and($docx->size)->toBeGreaterThan(0)
        ->and($pdf->size)->toBeGreaterThan(0);
});

it('remplace les pièces jointes existantes plutôt que d’en accumuler à chaque régénération', function () {
    (new GenerateReportDocumentJob($this->report->id))->handle(
        app(DocxReportGenerator::class),
        app(DocxToPdfService::class),
    );
    (new GenerateReportDocumentJob($this->report->id))->handle(
        app(DocxReportGenerator::class),
        app(DocxToPdfService::class),
    );

    expect($this->report->fresh()->attachments)->toHaveCount(2);
});
