<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Brouillon = 'brouillon';
    case Finalise = 'finalise';
    case Signe = 'signe';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Finalise => 'Finalisé',
            self::Signe => 'Signé',
        };
    }
}
