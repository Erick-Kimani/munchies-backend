<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertySubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'listing_type',
        'full_name',
        'email',
        'phone',
        'price_range',
        'location',
        'description',
        'photo_path',
        'latitude',
        'longitude',
    ];

    // status, review_note, reviewed_by, reviewed_at are deliberately left
    // out of $fillable — they are never set from submitter input, only
    // from the admin-only review actions in the controller.

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'reviewed_at' => 'datetime',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute()
    {
        return $this->photo_path ? asset('storage/' . $this->photo_path) : null;
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
