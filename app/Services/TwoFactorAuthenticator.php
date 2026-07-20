<?php

namespace App\Services;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Génère, envoie et vérifie le code de connexion à deux facteurs par e-mail (F1).
 */
class TwoFactorAuthenticator
{
    private const CODE_VALIDITY_MINUTES = 10;

    public function challenge(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'two_factor_code' => Hash::make($code),
            'two_factor_expires_at' => now()->addMinutes(self::CODE_VALIDITY_MINUTES),
        ])->save();

        Mail::to($user->email)->send(new TwoFactorCodeMail($code));
    }

    public function verify(User $user, string $code): bool
    {
        if (! $user->two_factor_code || ! $user->two_factor_expires_at) {
            return false;
        }

        if ($user->two_factor_expires_at->isPast()) {
            return false;
        }

        $valid = Hash::check($code, $user->two_factor_code);

        if ($valid) {
            $user->forceFill([
                'two_factor_code' => null,
                'two_factor_expires_at' => null,
            ])->save();
        }

        return $valid;
    }
}
