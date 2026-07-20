<?php

use App\Services\BackupService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

afterEach(function () {
    foreach (Storage::disk('local')->files('backups') as $file) {
        Storage::disk('local')->delete($file);
    }
    File::deleteDirectory(storage_path('app/private/backups-tmp'));
});

it('crée une archive contenant le dump de la base et les templates institutionnels', function () {
    $path = app(BackupService::class)->run();

    expect(Storage::disk('local')->exists($path))->toBeTrue();

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($path));

    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    $zip->close();

    expect($names)->toContain('database.sqlite');
    expect(collect($names)->filter(fn ($n) => str_starts_with($n, 'templates/'))->count())->toBeGreaterThan(0);
});

it('conserve seulement les N dernières sauvegardes (rotation)', function () {
    config(['radiology.backup_keep' => 2]);

    app(BackupService::class)->run();
    app(BackupService::class)->run();
    app(BackupService::class)->run();

    $remaining = collect(Storage::disk('local')->files('backups'))
        ->filter(fn ($f) => str_ends_with($f, '.zip'));

    expect($remaining)->toHaveCount(2);
});
