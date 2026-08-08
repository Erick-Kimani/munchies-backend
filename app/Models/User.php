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
        'role_id'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

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