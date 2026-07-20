<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticator $twoFactor,
        private readonly AuditLogger $audit,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => 'Compte verrouillé suite à plusieurs échecs de connexion. Réessayez dans 15 minutes.',
            ]);
        }

        if (! $user || ! $user->active || ! Auth::validate($credentials)) {
            $user?->registerFailedLogin();
            $this->audit->log('connexion_echec', $user, request: $request);

            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        if ($user->two_factor_enabled) {
            $this->twoFactor->challenge($user);
            $request->session()->put('2fa.user_id', $user->id);

            return redirect()->route('admin.login.two-factor');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->registerSuccessfulLogin($request->ip());
        $this->audit->log('connexion_reussie', $user, request: $request);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function showTwoFactor(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('2fa.user_id')) {
            return redirect()->route('admin.login');
        }

        return view('auth.two-factor');
    }

    public function verifyTwoFactor(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $userId = $request->session()->get('2fa.user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user || ! $this->twoFactor->verify($user, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'Code invalide ou expiré.',
            ]);
        }

        $request->session()->forget('2fa.user_id');
        Auth::login($user);
        $request->session()->regenerate();
        $user->registerSuccessfulLogin($request->ip());
        $this->audit->log('connexion_reussie_2fa', $user, request: $request);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->audit->log('deconnexion', $request->user(), request: $request);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
