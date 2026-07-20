<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Conversion DOCX -> PDF via LibreOffice headless (F3). Utilisé en job de
 * queue car la conversion peut prendre plusieurs secondes.
 */
class DocxToPdfService
{
    /**
     * @return string chemin absolu du PDF généré (même dossier que le DOCX source)
     */
    public function convert(string $docxPath): string
    {
        if (! is_file($docxPath)) {
            throw new RuntimeException("Fichier DOCX introuvable : {$docxPath}");
        }

        $outputDir = dirname($docxPath);
        $binary = config('radiology.libreoffice_binary', '/usr/bin/soffice');
        $profileDir = sys_get_temp_dir().'/lo-profile-'.uniqid();

        $process = new Process([
            $binary,
            '--headless',
            '--invisible',
            '--norestore',
            '-env:UserInstallation=file://'.$profileDir,
            '--convert-to', 'pdf',
            '--outdir', $outputDir,
            $docxPath,
        ]);

        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Échec de la conversion PDF : '.$process->getErrorOutput());
        }

        $pdfPath = $outputDir.'/'.pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';

        if (! is_file($pdfPath)) {
            throw new RuntimeException("La conversion n'a pas produit de fichier PDF attendu : {$pdfPath}");
        }

        return $pdfPath;
    }
}
