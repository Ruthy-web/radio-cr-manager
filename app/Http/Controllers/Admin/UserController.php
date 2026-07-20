<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestion des comptes utilisateurs (F1/F9), réservée au rôle admin. Aucune
 * suppression physique (soft delete) : un compte désactivé reste dans
 * l'historique des comptes rendus qu'il a rédigés.
 */
class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $users = User::withTrashed()->orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'email', 'role', 'password', 'two_factor_enabled']);
        $data['two_factor_enabled'] = (bool) ($data['two_factor_enabled'] ?? false);
        $data['active'] = true;

        $user = User::create($data);
        $this->audit->log('utilisateur_cree', $request->user(), $user, $request);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Compte « {$user->name} » créé.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'email', 'role', 'password', 'two_factor_enabled']);
        $data['two_factor_enabled'] = (bool) ($data['two_factor_enabled'] ?? false);

        // Laisser le mot de passe vide conserve le mot de passe actuel.
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);
        $this->audit->log('utilisateur_modifie', $request->user(), $user, $request);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Compte « {$user->name} » mis à jour.");
    }

    /**
     * Désactivation (soft delete) — jamais de suppression physique.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'Impossible de désactiver son propre compte.');

        $user->update(['active' => false]);
        $user->tokens()->delete();
        $user->delete();
        $this->audit->log('utilisateur_desactive', $request->user(), $user, $request);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Compte « {$user->name} » désactivé.");
    }

    public function restore(int $user): RedirectResponse
    {
        $userModel = User::withTrashed()->findOrFail($user);
        $userModel->restore();
        $userModel->update(['active' => true]);
        $this->audit->log('utilisateur_reactive', request()->user(), $userModel, request());

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Compte « {$userModel->name} » réactivé.");
    }
}
