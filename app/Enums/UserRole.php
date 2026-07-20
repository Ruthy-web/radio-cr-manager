<?php

namespace App\Enums;

/**
 * Rôles applicatifs. La secrétaire peut lire et saisir l'identité patient
 * mais n'a jamais accès à la validation médicale d'un compte rendu.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Radiologue = 'radiologue';
    case Secretaire = 'secretaire';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Radiologue => 'Radiologue',
            self::Secretaire => 'Secrétaire',
        };
    }
}
