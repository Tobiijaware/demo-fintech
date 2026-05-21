<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'firstname',
        'lastname',
        'gender',
        'dob',
        'phone',
        'email',
        'user_type',
        'backoffice_role_id',
        'job_title',
        'hub',
        'role',
        'status',
        'account_tier',
        'bvn',
        'nin',
        'pin',
        'password',
    ];

    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'dob' => 'date',
            'user_type' => UserType::class,
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('currency', 'NGN');
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function kycVerifications(): HasMany
    {
        return $this->hasMany(KycVerification::class);
    }

    public function backofficeRole(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BackofficeRole::class, 'backoffice_role_id');
    }

    public function isBackofficeStaff(): bool
    {
        return $this->user_type === UserType::Staff
            || $this->role === UserRole::Admin;
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }

    public function hasPinSetup(): bool
    {
        return ! empty($this->pin);
    }

    public function getPinSetUpAttribute(): bool
    {
        return $this->hasPinSetup();
    }
}
