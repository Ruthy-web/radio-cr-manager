<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\DocxReportGenerator;
use App\Services\DocxToPdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Génère le DOCX final d'un compte rendu depuis le template institutionnel
 * réel, puis le convertit en PDF (F3). Mis en file d'attente car la
 * conversion LibreOffice peut prendre plusieurs secondes.
 */
class GenerateReportDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $reportId) {}

    public function handle(DocxReportGenerator $docxGenerator, DocxToPdfService $pdfConverter): void
    {
        $report = Report::findOrFail($this->reportId);

        $docxPath = $docxGenerator->generate($report);
        $this->storeAttachment($report, $docxPath, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        try {
            $pdfPath = $pdfConverter->convert($docxPath);
            $this->storeAttachment($report, $pdfPath, 'application/pdf');
        } catch (\Throwable $e) {
            // Le DOCX reste exploitable même si la conversion PDF échoue
            // (ex. LibreOffice indisponible) : on journalise sans faire échouer le job.
            Log::warning('Conversion PDF du compte rendu impossible', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function storeAttachment(Report $report, string $absolutePath, string $mime): void
    {
        $relativePath = 'reports/'.basename($absolutePath);

        $report->attachments()->updateOrCreate(
            ['type' => 'document', 'mime' => $mime],
            [
                'path' => $relativePath,
                'size' => filesize($absolutePath),
            ]
        );
    }
}
