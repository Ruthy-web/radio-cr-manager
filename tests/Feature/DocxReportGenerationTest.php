<?php

use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Models\Report;
use App\Services\DocxReportGenerator;

/**
 * Vérifie que la génération DOCX (F3) respecte R1 : le template institutionnel
 * réel n'est jamais reconstruit, seul son XML est modifié — entête/logo,
 * police Arial Narrow et absence de soulignement des titres doivent survivre
 * intacts, et seul l'examen demandé doit rester dans le document produit.
 */
beforeEach(function () {
    $this->hospital = Hospital::factory()->create([
        'name' => 'Clinique NKOULOU — Cabinet Polyclinique de la Cité',
        'header_docx_path' => 'templates/nkoulou.docx',
    ]);

    $this->exam = ExamTemplate::factory()->for($this->hospital)->create([
        'title' => 'Radiographie du Thorax',
        'heading' => 'Compte Rendu de Radiographie du Thorax',
    ]);

    $this->report = Report::factory()->for($this->hospital)->for($this->exam, 'examTemplate')->create([
        'patient_name' => 'Jean Essomba',
        'patient_age' => '54',
        'patient_sex' => 'M',
        'file_number' => 'DOS-12345',
        'prescriber' => 'Dr Ateba',
        'exam_date' => '2026-07-15',
        'content' => [
            'heading' => 'Compte Rendu de Radiographie du Thorax',
            'identity' => ['side' => null],
            'indication' => null,
            'technique' => 'Cliché standard de thorax de face, en inspiration profonde.',
            'results' => [
                ['text' => 'Résultat normal un.', 'abnormal' => false, 'heading' => false],
                ['text' => 'Opacité suspecte du lobe supérieur droit.', 'abnormal' => true, 'heading' => false],
                ['text' => 'Résultat normal trois.', 'abnormal' => false, 'heading' => false],
                ['text' => 'Résultat normal quatre.', 'abnormal' => false, 'heading' => false],
                ['text' => 'Résultat normal cinq.', 'abnormal' => false, 'heading' => false],
                ['text' => 'Résultat normal six.', 'abnormal' => false, 'heading' => false],
            ],
            'conclusion' => 'Opacité suspecte à explorer par TDM thoracique.',
        ],
    ]);
});

it('génère un DOCX en modifiant le template institutionnel réel sans le reconstruire', function () {
    $outputPath = app(DocxReportGenerator::class)->generate($this->report);

    expect($outputPath)->toBe(storage_path("app/private/reports/{$this->report->id}.docx"))
        ->and(is_file($outputPath))->toBeTrue();

    $sourceZip = new ZipArchive;
    $sourceZip->open(storage_path('app/templates/nkoulou.docx'));
    $outputZip = new ZipArchive;
    $outputZip->open($outputPath);

    // L'entête et le pied de page (logo, coordonnées de l'hôpital) sont des
    // parties DOCX distinctes, jamais touchées par la génération : elles
    // doivent rester bit-à-bit identiques à l'original (R1).
    expect($outputZip->getFromName('word/header1.xml'))->toBe($sourceZip->getFromName('word/header1.xml'))
        ->and($outputZip->getFromName('word/footer1.xml'))->toBe($sourceZip->getFromName('word/footer1.xml'));

    $documentXml = $outputZip->getFromName('word/document.xml');
    $sourceZip->close();
    $outputZip->close();

    // Police Arial Narrow conservée (héritée des runs d'origine, jamais réécrite).
    expect($documentXml)->toContain('Arial Narrow');

    // Seul l'examen demandé subsiste : un autre examen du catalogue Nkoulou
    // (Radiographie du Genou) ne doit plus apparaître dans le document.
    expect($documentXml)->not->toContain('Radiographie du Genou');

    // Identité du patient injectée dans les bons champs.
    expect($documentXml)->toContain('Jean Essomba')
        ->and($documentXml)->toContain('54')
        ->and($documentXml)->toContain('DOS-12345')
        ->and($documentXml)->toContain('Dr Ateba')
        ->and($documentXml)->toContain('15/07/2026');

    // Contenu médical substitué.
    expect($documentXml)->toContain('Cliché standard de thorax de face')
        ->and($documentXml)->toContain('Opacité suspecte à explorer par TDM thoracique');

    // Anomalie en rouge (R1 : #C00000).
    expect($documentXml)->toContain('C00000');

    $dom = new DOMDocument;
    $dom->loadXML($documentXml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    // Le titre de l'examen ne doit jamais être souligné, même si le
    // template source porte encore un <w:u/> hérité sur ce paragraphe.
    $titleParagraphs = $xpath->query("//w:p[contains(., 'Radiographie du Thorax')]");
    expect($titleParagraphs->length)->toBeGreaterThan(0);

    foreach ($titleParagraphs as $titleParagraph) {
        expect($xpath->query('.//w:u', $titleParagraph)->length)->toBe(0);
    }
});

it('refuse de générer un document si le compte rendu n’a pas d’examen du catalogue', function () {
    $this->report->update(['exam_template_id' => null]);

    app(DocxReportGenerator::class)->generate($this->report);
})->throws(RuntimeException::class);

it('refuse de générer un document si l’hôpital n’a pas de template DOCX', function () {
    $this->hospital->update(['header_docx_path' => null]);

    app(DocxReportGenerator::class)->generate($this->report);
})->throws(RuntimeException::class);
