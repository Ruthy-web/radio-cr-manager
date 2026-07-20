<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sauvegarde quotidienne (F7) : base de données + templates institutionnels
// + documents déjà générés, avec rotation (BACKUP_KEEP, 14 par défaut).
Schedule::command('app:backup')->dailyAt('02:00')->onOneServer();
