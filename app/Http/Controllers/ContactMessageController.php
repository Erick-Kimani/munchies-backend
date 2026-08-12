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
}