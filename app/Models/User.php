<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'google_id',
        'avatar',
        'password_set_at',
        'password_set_override',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        // Internal admin-override flag — the frontend only needs the
        // derived can_set_password boolean (see below), not this raw flag.
        'password_set_override',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'password_set_at' => 'datetime',
        'password_set_override' => 'boolean',
    ];

    // Included on every serialized User so the frontend can decide whether
    // to show the "Set password" link / allow the page, without having to
    // know about password_set_at/password_set_override individually.
    protected $appends = [
        'can_set_password',
    ];

    /**
     * Whether this account is currently allowed to go through
     * POST /set-password: either it's never been used before, or an admin
     * has granted a one-time override after the fact.
     */
    public function getCanSetPasswordAttribute(): bool
    {
        return is_null($this->password_set_at) || (bool) $this->password_set_override;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function isAdmin()
    {
        return $this->role->id === 1;
    }

    public function isSeller()
    {
        return $this->role->id === 2;
    }

    public function isUser()
    {
        return $this->role->id === 3;
    }

    public function abilities()
    {
        return [
            'admin' => $this->isAdmin(),
            'seller' => $this->isSeller(),
            'user' => $this->isUser(),
        ];
    }
}