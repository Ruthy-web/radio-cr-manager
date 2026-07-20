<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature = 'app:backup';

    protected $description = 'Sauvegarde la base de données et les fichiers institutionnels (F7)';

    public function handle(BackupService $backup): int
    {
        $path = $backup->run();
        $this->info("Sauvegarde créée : {$path}");

        return self::SUCCESS;
    }
}
