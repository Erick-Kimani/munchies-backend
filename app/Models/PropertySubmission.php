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
        'photo_path_2',
        'photo_path_3',
        'latitude',
        'longitude',
    ];

    // status, review_note, reviewed_by, reviewed_at, payment_id are
    // deliberately left out of $fillable — none of them are ever set
    // from submitter input. status/review fields only from the
    // admin-only review actions in the controller; payment_id only from
    // PropertySubmissionController::store's own verified Payment lookup,
    // never from the request body directly.

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'reviewed_at' => 'datetime',
        'featured_at' => 'datetime',
    ];

    protected $appends = ['photo_url', 'photo_url_2', 'photo_url_3', 'photo_urls'];

    public function getPhotoUrlAttribute()
    {
        return $this->photo_path ? asset('storage/' . $this->photo_path) : null;
    }

    public function getPhotoUrl2Attribute()
    {
        return $this->photo_path_2 ? asset('storage/' . $this->photo_path_2) : null;
    }

    public function getPhotoUrl3Attribute()
    {
        return $this->photo_path_3 ? asset('storage/' . $this->photo_path_3) : null;
    }

    // Convenience array of every photo actually set on this listing (1 to
    // 3 URLs, in upload order), with any empty slots dropped — this is
    // what the frontend carousel (PropertyEnquiryModal.vue) consumes
    // directly instead of stitching photo_url/photo_url_2/photo_url_3
    // together itself.
    public function getPhotoUrlsAttribute()
    {
        return array_values(array_filter([
            $this->photo_url,
            $this->photo_url_2,
            $this->photo_url_3,
        ]));
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}