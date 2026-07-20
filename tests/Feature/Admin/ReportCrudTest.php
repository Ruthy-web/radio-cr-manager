<?php

use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Models\Report;
use App\Models\ReportVersion;
use App\Models\User;

it('permet à un radiologue de créer un compte rendu avec contenu médical', function () {
    $radiologue = User::factory()->create();
    $hospital = Hospital::factory()->create();
    $exam = ExamTemplate::factory()->for($hospital)->create();

    $response = $this->actingAs($radiologue)->post(route('admin.reports.store'), [
        'hospital_id' => $hospital->id,
        'exam_template_id' => $exam->id,
        'patient_name' => 'Jean Dupont',
        'patient_age' => '45',
        'patient_sex' => 'M',
        'heading' => 'Compte Rendu de Radiographie du Thorax',
        'technique' => 'Cliché standard',
        'results_text' => "Ligne un.\nLigne deux.",
        'conclusion' => 'Normal.',
    ]);

    $report = Report::firstOrFail();
    $response->assertRedirect(route('admin.reports.edit', $report));

    expect($report->patient_name)->toBe('Jean Dupont')
        ->and($report->content['technique'])->toBe('Cliché standard')
        ->and($report->content['results'])->toHaveCount(2)
        ->and($report->status->value)->toBe('brouillon')
        ->and($report->versions()->count())->toBe(1);
});

it('clone le contenu du template lorsqu’une secrétaire crée un compte rendu, sans lui permettre de le rédiger', function () {
    $secretaire = User::factory()->secretaire()->create();
    $hospital = Hospital::factory()->create();
    $exam = ExamTemplate::factory()->for($hospital)->create([
        'technique' => 'Technique du template',
        'conclusion' => 'Conclusion du template',
    ]);

    $this->actingAs($secretaire)->post(route('admin.reports.store'), [
        'hospital_id' => $hospital->id,
        'exam_template_id' => $exam->id,
        'patient_name' => 'Marie Curie',
        // Même si elle tentait d'envoyer un contenu médical, il est ignoré côté serveur.
        'technique' => 'Tentative de contenu médical',
    ]);

    $report = Report::firstOrFail();
    expect($report->content['technique'])->toBe('Technique du template')
        ->and($report->content['conclusion'])->toBe('Conclusion du template');
});

it('crée une nouvelle version à chaque modification du contenu médical', function () {
    $radiologue = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $radiologue->id]);
    expect($report->versions()->count())->toBe(1);

    $this->actingAs($radiologue)->put(route('admin.reports.update', $report), [
        'hospital_id' => $report->hospital_id,
        'patient_name' => $report->patient_name,
        'heading' => 'Nouveau titre',
        'technique' => 'Nouvelle technique',
        'results_text' => 'Nouveau résultat.',
        'conclusion' => 'Nouvelle conclusion.',
    ]);

    expect($report->fresh()->versions()->count())->toBe(2)
        ->and($report->fresh()->content['technique'])->toBe('Nouvelle technique');
});

it('permet de restaurer une version antérieure', function () {
    $radiologue = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $radiologue->id]);
    $originalContent = $report->content;

    $report->update(['content' => [...$originalContent, 'conclusion' => 'Conclusion modifiée']]);
    expect($report->fresh()->versions()->count())->toBe(2);

    $firstVersion = ReportVersion::where('report_id', $report->id)->oldest('id')->first();

    $this->actingAs($radiologue)->post(route('admin.reports.versions.restore', [$report, $firstVersion]));

    expect($report->fresh()->content['conclusion'])->toBe($originalContent['conclusion'])
        ->and($report->fresh()->versions()->count())->toBe(3);
});

it('interdit à une secrétaire de finaliser ou signer un compte rendu', function () {
    $secretaire = User::factory()->secretaire()->create();
    $report = Report::factory()->create();

    $this->actingAs($secretaire)->post(route('admin.reports.finalize', $report))->assertForbidden();
    $this->actingAs($secretaire)->post(route('admin.reports.sign', $report))->assertForbidden();
});

it('fait progresser le statut de brouillon à finalisé puis signé', function () {
    $radiologue = User::factory()->create();
    $report = Report::factory()->create();

    $this->actingAs($radiologue)->post(route('admin.reports.finalize', $report));
    expect($report->fresh()->status->value)->toBe('finalise');

    $this->actingAs($radiologue)->post(route('admin.reports.sign', $report));
    expect($report->fresh()->status->value)->toBe('signe');
});

it('archive un compte rendu par soft delete plutôt qu’une suppression physique', function () {
    $radiologue = User::factory()->create();
    $report = Report::factory()->create();

    $this->actingAs($radiologue)->delete(route('admin.reports.destroy', $report));

    $this->assertSoftDeleted('reports', ['id' => $report->id]);
});

it('regroupe l’historique par journée et filtre par nom de patient déchiffré', function () {
    $radiologue = User::factory()->create();
    $hospital = Hospital::factory()->create();

    Report::factory()->for($hospital)->create([
        'user_id' => $radiologue->id,
        'patient_name' => 'Alice Martin',
        'exam_date' => '2026-07-10',
    ]);
    Report::factory()->for($hospital)->create([
        'user_id' => $radiologue->id,
        'patient_name' => 'Bob Sanogo',
        'exam_date' => '2026-07-11',
    ]);

    $response = $this->actingAs($radiologue)->get(route('admin.reports.index', ['patient_name' => 'alice']));

    $response->assertOk();
    $response->assertSee('Alice Martin');
    $response->assertDontSee('Bob Sanogo');
});

it('redirige un visiteur non authentifié vers la connexion', function () {
    $this->get(route('admin.reports.index'))->assertRedirect(route('admin.login'));
});
