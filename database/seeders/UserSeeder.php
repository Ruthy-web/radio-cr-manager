<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comptes de démonstration (F10) : 1 administrateur, 1 radiologue.
 * Mots de passe à changer impérativement en production.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@radio-cr-manager.local'],
            [
                'name' => 'Administrateur',
                'password' => 'Admin!2026Demo',
                'role' => UserRole::Admin,
                'active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'radiologue@radio-cr-manager.local'],
            [
                'name' => 'Dr E. NDONGO',
                'password' => 'Radio!2026Demo',
                'role' => UserRole::Radiologue,
                'active' => true,
            ]
        );
    }
}
