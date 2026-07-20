<?php

use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Models\Report;
use App\Models\User;

beforeEach(function () {
    $hospital = Hospital::factory()->create(['header_docx_path' => 'templates/nkoulou.docx']);
    $exam = ExamTemplate::factory()->for($hospital)->create([
        'title' => 'Radiographie du Thorax',
        'heading' => 'Compte Rendu de Radiographie du Thorax',
    ]);

    $this->report = Report::factory()->for($hospital)->for($exam, 'examTemplate')->create();
});

it('permet à un radiologue de générer le document depuis la fiche du compte rendu', function () {
    $radiologue = User::factory()->create();

    $response = $this->actingAs($radiologue)->post(route('admin.reports.document.generate', $this->report));

    $response->assertRedirect(route('admin.reports.edit', $this->report));
    expect($this->report->fresh()->attachments)->toHaveCount(2);
});

it('interdit à une secrétaire de générer le document', function () {
    $secretaire = User::factory()->secretaire()->create();

    $this->actingAs($secretaire)
        ->post(route('admin.reports.document.generate', $this->report))
        ->assertForbidden();

    expect($this->report->fresh()->attachments)->toHaveCount(0);
});

it('permet de télécharger une pièce jointe générée', function () {
    $radiologue = User::factory()->create();
    $this->actingAs($radiologue)->post(route('admin.reports.document.generate', $this->report));

    $attachment = $this->report->fresh()->attachments->firstWhere('mime', 'application/pdf');

    $response = $this->actingAs($radiologue)
        ->get(route('admin.reports.attachments.download', [$this->report, $attachment]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

it('refuse de télécharger une pièce jointe qui n’appartient pas au compte rendu', function () {
    $radiologue = User::factory()->create();
    $this->actingAs($radiologue)->post(route('admin.reports.document.generate', $this->report));
    $attachment = $this->report->fresh()->attachments->first();

    $otherReport = Report::factory()->create();

    $this->actingAs($radiologue)
        ->get(route('admin.reports.attachments.download', [$otherReport, $attachment]))
        ->assertNotFound();
});
