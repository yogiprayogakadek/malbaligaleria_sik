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
        'must_change_password',
        'password_changed_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
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

        if ($this->isSecretary()) {
            return 'secretary.index';
        }

        return 'portal.dashboard';
    }
}
