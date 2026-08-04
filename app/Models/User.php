<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property Carbon|null $email_verified_at
 * @property string      $password
 * @property string      $role              'admin' | 'user'
 * @property bool        $is_active
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    // ─── Casts ──────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active'               => 'boolean',
        ];
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    /** Videos uploaded by this admin user */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'admin_id');
    }

    /** Watch resume positions */
    public function resumes(): HasMany
    {
        return $this->hasMany(VideoResume::class);
    }

    /** Completed video watches */
    public function watches(): HasMany
    {
        return $this->hasMany(VideoWatch::class);
    }

    /** Quiz attempt records */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
