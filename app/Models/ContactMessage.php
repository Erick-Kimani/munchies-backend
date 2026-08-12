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
}