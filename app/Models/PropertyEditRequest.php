<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyEditRequest extends Model
{
    // A listing gets 2 edit requests total, ever -- counted against ALL
    // requests regardless of outcome (a rejected request still uses up
    // one of the two), not just approved ones. See
    // PropertyEditRequestController::store for where this is enforced.
    public const MAX_PER_SUBMISSION = 2;

    // Every column a request can propose a change to. The three photo_*
    // columns hold paths to the proposed replacement images.
    public const EDITABLE_FIELDS = [
        'type', 'description', 'latitude', 'longitude', 'phone',
        'photo_path', 'photo_path_2', 'photo_path_3',
    ];

    public const PHOTO_COLUMNS = ['photo_path', 'photo_path_2', 'photo_path_3'];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'property_submission_id',
        'requested_by',
        'type',
        'description',
        'latitude',
        'longitude',
        'phone',
        'photo_path',
        'photo_path_2',
        'photo_path_3',
        'seller_note',
    ];

    protected $appends = ['photo_url', 'photo_url_2', 'photo_url_3'];

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

    // status, admin_note, reviewed_by, reviewed_at are deliberately left
    // out of $fillable -- identical reasoning to PropertySubmission: none
    // of these are ever set from the seller's request body, only from
    // the admin-only approve()/reject() actions.

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(PropertySubmission::class, 'property_submission_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // The set of columns this request actually proposes a change to --
    // i.e. the non-null ones among the editable fields. Used by
    // PropertyEditRequestController::approve() to apply only what was
    // actually asked for, and by the frontend to show "this request
    // changes: Type, Description" without re-deriving it client-side.
    public function changedFields(): array
    {
        return collect(self::EDITABLE_FIELDS)
            ->filter(fn ($field) => ! is_null($this->{$field}))
            ->values()
            ->all();
    }
}