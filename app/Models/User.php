<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    protected $attributes = [
        'is_active' => true,
        'must_change_password' => false,
        'whatsapp_notifications_enabled' => false,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'tenant_name',
        'role',
        'division',
        'is_active',
        'whatsapp_notifications_enabled',
        'must_change_password',
        'password_changed_at',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'two_factor_last_used_step',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'is_active' => 'boolean',
            'whatsapp_notifications_enabled' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_step' => 'integer',
        ];
    }

    /**
     * Cek apakah role adalah admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Cek apakah role adalah tenant.
     */
    public function isTenant(): bool
    {
        return $this->role === 'tenant';
    }

    /**
     * Cek apakah role adalah validator.
     */
    public function isValidator(): bool
    {
        return $this->role === 'validator';
    }

    public function isSecretary(): bool
    {
        return $this->role === 'secretary';
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isValidator() || $this->isSecretary();
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function dashboardRouteName(): string
    {
        if ($this->isAdmin()) {
            return 'admin.dashboard';
        }

        if ($this->isValidator() && $this->division === 'TR') {
            return 'tr.index';
        }

        if ($this->isValidator() && $this->division === 'MEP') {
            return 'mep.index';
        }

        if ($this->isValidator() && $this->division === 'FIN') {
            return 'finance.index';
        }

        if ($this->isValidator() && $this->division === 'EP') {
            return 'ep.index';
        }

        if ($this->isSecretary()) {
            return 'secretary.index';
        }

        return 'portal.dashboard';
    }
}
