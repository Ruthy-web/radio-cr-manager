<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'active',
        'two_factor_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_expires_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Enregistre un échec de connexion et verrouille le compte 15 minutes
     * après le 5e échec consécutif (F1).
     */
    public function registerFailedLogin(): void
    {
        $this->failed_login_attempts++;

        if ($this->failed_login_attempts >= 5) {
            $this->locked_until = now()->addMinutes(15);
            $this->failed_login_attempts = 0;
        }

        $this->save();
    }

    public function registerSuccessfulLogin(string $ip): void
    {
        $this->failed_login_attempts = 0;
        $this->locked_until = null;
        $this->last_login_at = now();
        $this->last_login_ip = $ip;
        $this->save();
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
