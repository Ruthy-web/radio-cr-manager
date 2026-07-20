<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authentification API pour la PWA : jetons Sanctum (F1/F6).
 * Stateless : le défi 2FA est porté par un jeton court terme en cache,
 * jamais par la session (l'API n'utilise pas de cookies de session).
 */
class AuthController extends Controller
{
    private const CHALLENGE_TTL_MINUTES = 10;

    public function __construct(
        private readonly TwoFactorAuthenticator $twoFactor,
        private readonly AuditLogger $audit,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => ['Compte verrouillé suite à plusieurs échecs de connexion. Réessayez dans 15 minutes.'],
            ]);
        }

        if (! $user || ! $user->active || ! Auth::validate($credentials)) {
            $user?->registerFailedLogin();
            $this->audit->log('connexion_echec', $user, request: $request);

            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        if ($user->two_factor_enabled) {
            $this->twoFactor->challenge($user);

            $challengeToken = Str::random(40);
            Cache::put("2fa_challenge:{$challengeToken}", $user->id, now()->addMinutes(self::CHALLENGE_TTL_MINUTES));

            return response()->json([
                'two_factor_required' => true,
                'challenge_token' => $challengeToken,
            ]);
        }

        $user->registerSuccessfulLogin($request->ip());
        $this->audit->log('connexion_reussie', $user, request: $request);

        return response()->json([
            'two_factor_required' => false,
            'token' => $user->createToken('pwa')->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $userId = Cache::get("2fa_challenge:{$validated['challenge_token']}");
        $user = $userId ? User::find($userId) : null;

        if (! $user || ! $this->twoFactor->verify($user, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => ['Code invalide ou expiré.'],
            ]);
        }

        Cache::forget("2fa_challenge:{$validated['challenge_token']}");
        $user->registerSuccessfulLogin($request->ip());
        $this->audit->log('connexion_reussie_2fa', $user, request: $request);

        return response()->json([
            'token' => $user->createToken('pwa')->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->audit->log('deconnexion', $request->user(), request: $request);
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ];
    }
}
