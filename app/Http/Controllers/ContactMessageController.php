<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    // Requires auth:sanctum — any logged-in user (admin, seller, or
    // plain user) can send one. The sender is taken from the token,
    // never from request input, so there's no email field to fill in
    // and no way to spoof who it's from.
    public function store(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|min:5|max:3000',
        ]);

        $message = ContactMessage::create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
        ]);

        return response()->json([
            'message' => 'Your message has been sent. Our team will get back to you.',
            'contact_message' => $message,
        ], 201);
    }

    // Any authenticated user — their own messages only, each with its full
    // reply thread. This is what powers Contact.vue's "your messages"
    // view. Deliberately scoped to auth()->id() so a plain user/seller can
    // never see anyone else's thread — index()/show() below stay admin-only
    // for that reason.
    public function mine(Request $request)
    {
        $messages = ContactMessage::with('replies.user:id,name,role_id')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($messages);
    }

    // Admin only — the single inbox every message lands in.
    public function index(Request $request)
    {
        $query = ContactMessage::with([
            'sender:id,name,email,role_id',
            'replies.user:id,name,role_id',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->paginate(20));
    }

    // Admin only.
    public function show($id)
    {
        return response()->json(
            ContactMessage::with([
                'sender:id,name,email,role_id',
                'replies.user:id,name,role_id',
            ])->findOrFail($id)
        );
    }

    // Admin only — mark as read without necessarily resolving it yet.
    public function markRead(Request $request, $id)
    {
        $message = ContactMessage::findOrFail($id);

        if ($message->status === 'new') {
            $message->update([
                'status' => 'read',
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Marked as read.',
            'contact_message' => $message,
        ]);
    }

    // Admin only — mark as fully handled.
    public function resolve(Request $request, $id)
    {
        $message = ContactMessage::findOrFail($id);

        $message->update([
            'status' => 'resolved',
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return response()->json([
            'message' => 'Marked as resolved.',
            'contact_message' => $message,
        ]);
    }

    // Admin only — the "Reply" action. Appends the admin's reply to the
    // thread (is_admin = true) rather than emailing anything out — replies
    // live in-app, the same way the original message did. Doesn't touch an
    // already-'resolved' status; replying to a resolved thread is just a
    // follow-up, not a reason to reopen it.
    public function reply(Request $request, $id)
    {
        $validated = $request->validate([
            'reply' => 'required|string|min:2|max:3000',
        ]);

        $message = ContactMessage::findOrFail($id);

        $message->replies()->create([
            'user_id' => $request->user()->id,
            'is_admin' => true,
            'body' => $validated['reply'],
        ]);

        $message->update([
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
            'status' => $message->status === 'resolved' ? 'resolved' : 'replied',
        ]);

        return response()->json([
            'message' => 'Reply sent.',
            'contact_message' => $message->load('replies.user:id,name,role_id'),
        ]);
    }
}