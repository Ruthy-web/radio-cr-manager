<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Binaire LibreOffice (conversion DOCX -> PDF, F3)
    |--------------------------------------------------------------------------
    */
    'libreoffice_binary' => env('LIBREOFFICE_BINARY', '/usr/bin/soffice'),

    /*
    |--------------------------------------------------------------------------
    | Sauvegardes (F7)
    |--------------------------------------------------------------------------
    */
    'backup_disk' => env('BACKUP_DISK', 'local'),
    'backup_keep' => env('BACKUP_KEEP', 14),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),

];
