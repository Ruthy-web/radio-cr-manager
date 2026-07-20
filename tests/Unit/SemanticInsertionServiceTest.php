<?php

use App\Services\SemanticInsertionService;

beforeEach(function () {
    $this->service = new SemanticInsertionService;
});

function makeResult(string $text, bool $heading = false): array
{
    return ['text' => $text, 'abnormal' => false, 'heading' => $heading];
}

it('remplace la ligne silhouette cardiaque / index cardio-thoracique avec "battement cardiaque = 377 bpm"', function () {
    $results = [
        makeResult('Transparence pulmonaire normale et symétrique. Pas d’opacité, de masse ni d’hyperclarté suspecte. Vascularisation pulmonaire régulière.'),
        makeResult('Médiastin de largeur normale, centré, sans masse ni adénopathie visible. Bouton aortique en place.'),
        makeResult('Silhouette cardiaque de morphologie normale. Index cardio-thoracique inférieur à 0,50.'),
        makeResult('Coupoles diaphragmatiques régulières, en position et hauteur normales. Angles costodiaphragmatiques dégagés.'),
        makeResult('Culs-de-sac costodiaphragmatiques libres et symétriques. Pas d’épanchement ni d’épaississement pleural.'),
        makeResult('Arcs costaux, clavicules, omoplates et parties molles d’aspect normal. Pas de lyse ni fracture visible.'),
    ];

    $result = $this->service->insert('battement cardiaque = 377 bpm', $results, 'Radiographie thoracique normale.');

    expect($result['replaced'])->toBe(1)
        ->and($result['added'])->toBe(0);

    $cardiacLine = $result['results'][2]['text'];
    expect($cardiacLine)->toContain('377 bpm')
        ->and($cardiacLine)->toStartWith('Silhouette cardiaque');

    // Les autres lignes ne sont pas affectées.
    expect($result['results'][0]['text'])->toContain('Transparence pulmonaire normale');
});

it('remplace la ligne de la grande veine saphène du bon côté avec "veine saphène gauche obstruée"', function () {
    $results = [
        makeResult('Grande veine saphène droite de calibre normal, sans reflux tronculaire.'),
        makeResult('Grande veine saphène gauche de calibre normal, sans reflux tronculaire.'),
    ];

    $result = $this->service->insert('veine saphène gauche obstruée', $results, 'Examen doppler veineux normal des deux côtés.');

    expect($result['replaced'])->toBe(1);

    // La ligne DROITE reste inchangée.
    expect($result['results'][0]['text'])->toBe('Grande veine saphène droite de calibre normal, sans reflux tronculaire.');

    // La ligne GAUCHE est remplacée par la constatation dictée, marquée anormale.
    expect($result['results'][1]['text'])->toContain('saphène gauche')
        ->and($result['results'][1]['text'])->toContain('obstru')
        ->and($result['results'][1]['abnormal'])->toBeTrue();
});

it('remplace la ligne « Transparence pulmonaire normale » avec "opacité alvéolaire du lobe supérieur droit"', function () {
    $results = [
        makeResult('Transparence pulmonaire normale et symétrique. Pas d’opacité, de masse ni d’hyperclarté suspecte. Vascularisation pulmonaire régulière.'),
        makeResult('Médiastin de largeur normale, centré, sans masse ni adénopathie visible. Bouton aortique en place.'),
        makeResult('Silhouette cardiaque de morphologie normale. Index cardio-thoracique inférieur à 0,50.'),
        makeResult('Coupoles diaphragmatiques régulières, en position et hauteur normales. Angles costodiaphragmatiques dégagés.'),
        makeResult('Culs-de-sac costodiaphragmatiques libres et symétriques. Pas d’épanchement ni d’épaississement pleural.'),
        makeResult('Arcs costaux, clavicules, omoplates et parties molles d’aspect normal. Pas de lyse ni fracture visible.'),
    ];

    $result = $this->service->insert(
        'opacité alvéolaire du lobe supérieur droit',
        $results,
        'Radiographie thoracique normale.'
    );

    expect($result['replaced'])->toBe(1);

    $pulmonaryLine = $result['results'][0]['text'];
    expect($pulmonaryLine)->toContain('Opacité alvéolaire')
        ->and($pulmonaryLine)->toContain('lobe supérieur droit')
        ->and($result['results'][0]['abnormal'])->toBeTrue();

    // La ligne cardiaque n'est pas touchée par erreur.
    expect($result['results'][2]['text'])->toContain('Silhouette cardiaque de morphologie normale');
});

it('réécrit la conclusion quand une anomalie est dictée alors que la conclusion dit encore « normal »', function () {
    $results = [makeResult('Transparence pulmonaire normale et symétrique.')];

    $result = $this->service->insert(
        'opacité alvéolaire du lobe supérieur droit',
        $results,
        'Radiographie thoracique normale.'
    );

    expect($result['conclusion'])->not->toBe('Radiographie thoracique normale.')
        ->and($result['conclusion'])->toContain('Opacité alvéolaire');
});

it('ne réécrit pas la conclusion si elle mentionne déjà une anomalie', function () {
    $results = [makeResult('Transparence pulmonaire normale et symétrique.')];

    $result = $this->service->insert(
        'opacité alvéolaire du lobe supérieur droit',
        $results,
        'Anomalie déjà connue, en cours de surveillance.'
    );

    expect($result['conclusion'])->toBe('Anomalie déjà connue, en cours de surveillance.');
});

it('prend en compte une phrase "conclusion : ..." explicite dans la dictée', function () {
    $results = [makeResult('Transparence pulmonaire normale et symétrique.')];

    $result = $this->service->insert(
        'conclusion : foyer de condensation basal droit évocateur d’une pneumopathie',
        $results,
        'Radiographie thoracique normale.'
    );

    expect($result['conclusion'])->toContain('pneumopathie');
});

it('n’insère jamais dans une ligne marquée comme sous-titre d’organe', function () {
    $results = [
        makeResult('Foie', heading: true),
        makeResult('Foie de taille normale, de contours réguliers.'),
    ];

    $result = $this->service->insert('foie augmenté de taille, contours bosselés', $results, 'Échographie normale.');

    expect($result['results'][0]['text'])->toBe('Foie')
        ->and($result['results'][0]['heading'])->toBeTrue()
        ->and($result['results'][1]['text'])->toContain('bosselés');
});

it('ajoute une nouvelle ligne quand aucune constatation existante ne correspond', function () {
    $results = [makeResult('Transparence pulmonaire normale et symétrique.')];

    $result = $this->service->insert('vésicule biliaire alithiasique à paroi fine', $results, 'Radiographie thoracique normale.');

    expect($result['added'])->toBe(1)
        ->and($result['results'])->toHaveCount(2)
        ->and($result['results'][1]['text'])->toContain('Vésicule biliaire alithiasique');
});

it('calcule un score de correspondance positif entre jetons et une ligne partageant les mêmes synonymes', function () {
    $tokens = ['saphene', 'gauche', 'obstruee'];

    $scoreGauche = $this->service->matchScore($tokens, 'Grande veine saphène gauche perméable.');
    $scoreDroite = $this->service->matchScore($tokens, 'Grande veine saphène droite perméable.');

    expect($scoreGauche)->toBeGreaterThan($scoreDroite);
});
