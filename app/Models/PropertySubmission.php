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

    public function editRequests()
    {
        return $this->hasMany(PropertyEditRequest::class)->latest();
    }

    // How many of this listing's 2 total edit-request slots are still
    // free. Counts every request regardless of status (pending, approved,
    // AND rejected all count) -- see PropertyEditRequest::MAX_PER_SUBMISSION.
    //
    // Deliberately NOT in $appends: this runs a COUNT query, and
    // PropertySubmissionController::index() (the admin listing) can
    // return many rows at once -- auto-appending here would be an N+1 on
    // every admin page load. Call explicitly (e.g. via loadCount(
    // 'editRequests') + this accessor) only where it's actually needed:
    // PropertySubmissionController::mine() and
    // PropertyEditRequestController.
    public function getEditRequestsRemainingAttribute(): int
    {
        $used = $this->relationLoaded('editRequests')
            ? $this->editRequests->count()
            : $this->editRequests()->count();

        return max(0, PropertyEditRequest::MAX_PER_SUBMISSION - $used);
    }

    public function hasPendingEditRequest(): bool
    {
        $requests = $this->relationLoaded('editRequests')
            ? $this->editRequests
            : $this->editRequests()->get();

        return $requests->contains(fn ($r) => $r->status === PropertyEditRequest::STATUS_PENDING);
    }
}