<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

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

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function isAdmin()
    {
        return $this->role->id === 1;
    }

    public function isUser()
    {
        return $this->role->id === 2;
    }

    public function isTeacher()
    {
        return $this->role->id === 3;
    }

    public function isStudent()
    {
        return $this->role->id === 4;
    }

    public function abilities()
    {
        return [
            'admin' => $this->isAdmin(),
            'user' => $this->isUser(),
            'teacher' => $this->isTeacher(),
            'student' => $this->isStudent(),
        ];
    }
}