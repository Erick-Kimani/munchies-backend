<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'message',
    ];

    // status, handled_by, handled_at are deliberately left out of
    // $fillable — only ContactMessageController's admin actions set
    // them, never the sender's own request input.

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    // Every reply in this thread — both the user's follow-ups and the
    // admin's responses — ordered oldest-first so it reads like a chat.
    public function replies()
    {
        return $this->hasMany(ContactMessageReply::class)->orderBy('created_at');
    }
}