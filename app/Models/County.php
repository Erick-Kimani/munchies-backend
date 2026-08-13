<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class County extends Model
{
    protected $fillable = ['name', 'status'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopePulledDown(Builder $query): Builder
    {
        return $query->where('status', 'pulled_down');
    }
}