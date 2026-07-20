<?php

use App\Services\HospitalDocxParser;

beforeEach(function () {
    $this->parser = new HospitalDocxParser;
});

it('découpe le DOCX Nkoulou en un examen par bloc avec ses sections', function () {
    $result = $this->parser->parse(storage_path('app/templates/nkoulou.docx'));

    expect($result['exams'])->not->toBeEmpty();
    expect(count($result['exams']))->toBeGreaterThan(50);

    $thorax = collect($result['exams'])->firstWhere('title', 'Radiographie du Thorax');

    expect($thorax)->not->toBeNull()
        ->and($thorax['technique'])->toContain('Cliché')
        ->and($thorax['requires_side'])->toBeFalse()
        ->and($thorax['conclusion'])->toContain('normale')
        ->and($thorax['results'])->not->toBeEmpty()
        ->and($thorax['results'][0]['text'])->not->toStartWith('•');
});

it('détecte la latéralité portée par le titre', function () {
    $result = $this->parser->parse(storage_path('app/templates/nkoulou.docx'));

    $genou = collect($result['exams'])->firstWhere('title', 'Radiographie du Genou');

    expect($genou['requires_side'])->toBeTrue();
});

it('détecte la latéralité portée par le bloc identification (HMR1)', function () {
    $result = $this->parser->parse(storage_path('app/templates/hmr1.docx'));

    $genou = collect($result['exams'])->first(fn ($exam) => str_contains(mb_strtolower($exam['title']), 'genou'));

    expect($genou['requires_side'])->toBeTrue();
});

it('reconstitue le vrai nom d’examen depuis le champ "Examen :" pour un template en tableau (CHM)', function () {
    $result = $this->parser->parse(storage_path('app/templates/chm.docx'));

    expect(count($result['exams']))->toBeGreaterThan(20);

    $abdo = collect($result['exams'])->firstWhere('title', 'Échographie abdominale');
    expect($abdo)->not->toBeNull();
});

it('distingue les sous-titres d’organe en gras des constatations à puce (CHM)', function () {
    $result = $this->parser->parse(storage_path('app/templates/chm.docx'));

    $abdo = collect($result['exams'])->firstWhere('title', 'Échographie abdominale');

    $headings = collect($abdo['results'])->where('heading', true)->pluck('text');
    $findings = collect($abdo['results'])->where('heading', false);

    expect($headings)->toContain('Foie')
        ->and($findings->count())->toBeGreaterThan(0)
        ->and($findings->first()['text'])->not->toStartWith('•');
});

it('extrait la couleur dominante des titres pour chaque hôpital', function () {
    $zalom = $this->parser->parse(storage_path('app/templates/zalom.docx'));
    $chm = $this->parser->parse(storage_path('app/templates/chm.docx'));

    expect($zalom['colors']['primary'])->toBe('#2E7D32')
        ->and($chm['colors']['primary'])->toBe('#10609B');
});
