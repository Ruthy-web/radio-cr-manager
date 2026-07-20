<?php

use App\Models\ExamTemplate;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * L'assistant est testé avec un DOCX réel existant (zalom.docx) réutilisé
 * comme « nouvel hôpital » : le moteur de parsing ne connaît pas les 5
 * hôpitaux de départ, il analyse n'importe quel DOCX de comptes rendus
 * normaux de la même façon (F2 complet). storeAs() ne fait que lire ce
 * fichier (copie en flux), jamais le déplacer ni le supprimer.
 */
function demoHospitalDocx(): UploadedFile
{
    return new UploadedFile(
        storage_path('app/templates/zalom.docx'),
        'nouvel-hopital.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        test: true,
    );
}

afterEach(function () {
    foreach (glob(storage_path('app/templates/hopital-demo-*.docx')) ?: [] as $strayFile) {
        File::delete($strayFile);
    }
});

it('analyse un DOCX et affiche une prévisualisation corrigible', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.hospitals.import.analyze'), [
        'name' => 'Hôpital Démo Six',
        'radiologist_name' => 'Dr Demo',
        'template' => demoHospitalDocx(),
    ]);

    $response->assertOk()->assertViewIs('admin.hospitals.import.preview');

    $exams = $response->viewData('exams');
    expect($exams)->not->toBeEmpty();
    expect($response->viewData('token'))->toBeString();
    expect($response->viewData('name'))->toBe('Hôpital Démo Six');
});

it('rejette un fichier qui n’est pas un DOCX', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.hospitals.import.analyze'), [
        'name' => 'Hôpital Démo',
        'template' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ]);

    $response->assertSessionHasErrors('template');
});

it('crée l’hôpital et son catalogue d’examens après confirmation, et range le DOCX dans storage/app/templates', function () {
    $admin = User::factory()->admin()->create();

    $analyzeResponse = $this->actingAs($admin)->post(route('admin.hospitals.import.analyze'), [
        'name' => 'Hôpital Démo Six',
        'radiologist_name' => 'Dr Demo',
        'template' => demoHospitalDocx(),
    ]);

    $token = $analyzeResponse->viewData('token');
    $exams = $analyzeResponse->viewData('exams');

    // Reproduit fidèlement une resoumission de formulaire sans modification :
    // chaque champ texte renvoie sa valeur affichée, chaque case cochée
    // (requires_side) renvoie sa valeur, une case décochée n'envoie rien.
    $corrections = collect($exams)->map(fn ($exam) => [
        'title' => $exam['title'],
        'requires_side' => $exam['requires_side'] ? '1' : null,
    ])->all();

    $response = $this->actingAs($admin)->post(route('admin.hospitals.import.store'), [
        'token' => $token,
        'name' => 'Hôpital Démo Six',
        'slug' => 'hopital-demo-six',
        'radiologist_name' => 'Dr Demo',
        'colors' => ['primary' => '#2E7D32'],
        'exams' => $corrections,
    ]);

    $hospital = Hospital::where('slug', 'hopital-demo-six')->firstOrFail();
    $response->assertRedirect(route('admin.hospitals.edit', $hospital));

    expect($hospital->name)->toBe('Hôpital Démo Six')
        ->and($hospital->header_docx_path)->toBe('templates/hopital-demo-six.docx')
        ->and(is_file(storage_path('app/templates/hopital-demo-six.docx')))->toBeTrue();

    $examCount = ExamTemplate::where('hospital_id', $hospital->id)->count();
    expect($examCount)->toBe(count($exams));

    // Latéralité correctement préservée par la resoumission du formulaire.
    $sideAware = collect($exams)->filter(fn ($exam) => $exam['requires_side'])->first();
    if ($sideAware) {
        expect(ExamTemplate::where('hospital_id', $hospital->id)->where('title', $sideAware['title'])->first()->requires_side)->toBeTrue();
    }
});

it('permet de corriger un titre d’examen avant validation', function () {
    $admin = User::factory()->admin()->create();

    $analyzeResponse = $this->actingAs($admin)->post(route('admin.hospitals.import.analyze'), [
        'name' => 'Hôpital Démo Sept',
        'template' => demoHospitalDocx(),
    ]);

    $token = $analyzeResponse->viewData('token');
    $exams = $analyzeResponse->viewData('exams');

    $corrections = collect($exams)->map(fn ($exam) => ['title' => $exam['title'], 'requires_side' => null])->all();
    $corrections[0]['title'] = 'Titre corrigé manuellement';

    $this->actingAs($admin)->post(route('admin.hospitals.import.store'), [
        'token' => $token,
        'name' => 'Hôpital Démo Sept',
        'slug' => 'hopital-demo-sept',
        'exams' => $corrections,
    ]);

    $hospital = Hospital::where('slug', 'hopital-demo-sept')->firstOrFail();
    expect(ExamTemplate::where('hospital_id', $hospital->id)->where('title', 'Titre corrigé manuellement')->exists())->toBeTrue();
});

it('refuse la confirmation quand le jeton de prévisualisation a expiré ou est inconnu', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.hospitals.import.store'), [
        'token' => 'jeton-inconnu',
        'name' => 'X',
        'slug' => 'x',
    ]);

    $response->assertRedirect(route('admin.hospitals.import.create'));
    $response->assertSessionHasErrors('template');
});

it('interdit l’assistant d’import à un rôle non administrateur', function () {
    $radiologue = User::factory()->create();

    $this->actingAs($radiologue)->get(route('admin.hospitals.import.create'))->assertForbidden();
});
