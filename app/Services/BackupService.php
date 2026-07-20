<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Sauvegarde complète (F7) : dump de la base (chiffrement des champs
 * nominatifs déjà appliqué au repos, R3 — le dump ne contient donc jamais de
 * PHI en clair), templates institutionnels et documents déjà générés,
 * archivés en zip sur le disque configuré (`BACKUP_DISK`), avec rotation.
 * La clé applicative (APP_KEY) n'est jamais incluse dans l'archive.
 */
class BackupService
{
    /**
     * @return string chemin de l'archive sur le disque de sauvegarde
     */
    public function run(): string
    {
        // Le suffixe unique évite toute collision de nom si deux sauvegardes
        // sont déclenchées dans la même seconde (ex. relance manuelle).
        $filename = 'backup-'.now()->format('Y-m-d_His').'-'.uniqid().'.zip';
        $tmpZipPath = storage_path("app/private/backups-tmp/{$filename}");
        File::ensureDirectoryExists(dirname($tmpZipPath));

        $zip = new ZipArchive;

        if ($zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossible de créer l'archive de sauvegarde : {$tmpZipPath}");
        }

        $databaseDumpPath = $this->dumpDatabase();
        $zip->addFile($databaseDumpPath, 'database.'.pathinfo($databaseDumpPath, PATHINFO_EXTENSION));
        $this->addDirectory($zip, storage_path('app/templates'), 'templates');
        $this->addDirectory($zip, storage_path('app/private/reports'), 'reports');

        $zip->close();
        File::delete($databaseDumpPath);

        $disk = Storage::disk(config('radiology.backup_disk'));
        $destination = "backups/{$filename}";
        $stream = fopen($tmpZipPath, 'r');
        $disk->put($destination, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        File::delete($tmpZipPath);

        $this->pruneOldBackups($disk);

        return $destination;
    }

    /**
     * @return string chemin absolu temporaire du dump (sqlite copié ou mysqldump)
     */
    private function dumpDatabase(): string
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if ($connection === 'sqlite') {
            $dumpPath = storage_path('app/private/backups-tmp/database-'.uniqid().'.sqlite');
            File::ensureDirectoryExists(dirname($dumpPath));

            if ($config['database'] === ':memory:') {
                // Base en mémoire (tests uniquement) : rien de persistant à
                // copier — une vraie base sqlite locale est toujours un fichier.
                File::put($dumpPath, '');
            } else {
                File::copy($config['database'], $dumpPath);
            }

            return $dumpPath;
        }

        $dumpPath = storage_path('app/private/backups-tmp/database-'.uniqid().'.sql');
        File::ensureDirectoryExists(dirname($dumpPath));

        $process = new Process([
            config('radiology.mysqldump_binary', 'mysqldump'),
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? '3306'),
            '--user='.($config['username'] ?? ''),
            '--password='.($config['password'] ?? ''),
            '--single-transaction',
            '--result-file='.$dumpPath,
            $config['database'],
        ]);

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Échec du dump de la base de données : '.$process->getErrorOutput());
        }

        return $dumpPath;
    }

    private function addDirectory(ZipArchive $zip, string $directory, string $prefix): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (File::allFiles($directory) as $file) {
            $zip->addFile($file->getPathname(), $prefix.'/'.$file->getRelativePathname());
        }
    }

    private function pruneOldBackups(Filesystem $disk): void
    {
        $keep = (int) config('radiology.backup_keep', 14);
        $files = collect($disk->files('backups'))
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->sortByDesc(fn (string $path) => $path)
            ->values();

        foreach ($files->slice($keep) as $stale) {
            $disk->delete($stale);
        }
    }
}
