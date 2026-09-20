<?php

namespace App\Models;

use App\Enums\LocaleEnum;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $email
 * @property CarbonInterface|null $email_verified_at
 * @property string $password
 * @property bool $is_admin
 * @property LocaleEnum $locale
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['email', 'password', 'locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'locale' => LocaleEnum::class,
        ];
    }

    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    /**
     * @return HasMany<IssueReport, $this>
     */
    public function issueReports(): HasMany
    {
        return $this->hasMany(IssueReport::class);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function isMainAdmin(): bool
    {
        $email = config('admin.main_email');

        return is_string($email)
            && $email !== ''
            && strcasecmp($this->email, $email) === 0;
    }

    public const GAME_SESSIONS_LIMIT = 0;

    public function canCreateGameSession(): bool
    {
        if (self::GAME_SESSIONS_LIMIT <= 0) {
            return false;
        }

        return $this->gameSessions()->count() >= self::GAME_SESSIONS_LIMIT;
    }

    public function doesGameSessionAlreadyExist(int $sessionId): bool
    {
        return $this->gameSessions()
            ->where('game_session_id', $sessionId)
            ->count() >= 1;
    }
}
