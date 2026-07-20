<?php

use App\Models\ExamTemplate;
use App\Models\Hospital;
use Database\Seeders\HospitalCatalogSeeder;

it('seede les 5 hôpitaux et leur catalogue depuis templates.json', function () {
    (new HospitalCatalogSeeder)->run();

    expect(Hospital::count())->toBe(5)
        ->and(ExamTemplate::count())->toBeGreaterThan(150);

    $nkoulou = Hospital::where('slug', 'nkoulou')->firstOrFail();
    expect($nkoulou->examTemplates()->count())->toBeGreaterThan(50)
        ->and($nkoulou->primaryColor())->toBe('#45767B');
});

it('est idempotent : rejouer le seeder ne duplique pas les hôpitaux', function () {
    (new HospitalCatalogSeeder)->run();
    (new HospitalCatalogSeeder)->run();

    expect(Hospital::count())->toBe(5);
});
