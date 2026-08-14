<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageReplyMail;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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

    // Admin only — the single inbox every message lands in.
    public function index(Request $request)
    {
        $query = ContactMessage::with('sender:id,name,email,role_id')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->paginate(20));
    }

    // Admin only.
    public function show($id)
    {
        return response()->json(
            ContactMessage::with('sender:id,name,email,role_id')->findOrFail($id)
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

    // Admin only — the "Reply" action. Sends the admin's reply to the
    // sender's email (the one on their account, never something typed in
    // by the admin) and records it against the message. Doesn't overwrite
    // an already-'resolved' status — replying to a resolved thread is just
    // a follow-up, not a reason to reopen it.
    public function reply(Request $request, $id)
    {
        $validated = $request->validate([
            'reply' => 'required|string|min:2|max:3000',
        ]);

        $message = ContactMessage::with('sender')->findOrFail($id);

        Mail::to($message->sender->email)
            ->send(new ContactMessageReplyMail($message, $validated['reply']));

        $message->update([
            'admin_reply' => $validated['reply'],
            'replied_at' => now(),
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
            'status' => $message->status === 'resolved' ? 'resolved' : 'replied',
        ]);

        return response()->json([
            'message' => 'Reply sent.',
            'contact_message' => $message,
        ]);
    }
}