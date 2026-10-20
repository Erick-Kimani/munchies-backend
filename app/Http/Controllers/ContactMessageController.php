<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Role;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        $messages = ContactMessage::with('replies.user')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $messages->each(function ($message) {
        $message->makeHidden(['user_id', 'handled_by', 'handled_at', 'updated_at']);
        $message->replies->each(fn ($reply) => $reply->makeHidden(['user_id', 'updated_at']));
    });


        return response()->json($messages);
    }
        // Admin only — the "pick a user" list for starting a conversation.
    // Optional ?search= (name or email) and ?role=seller|user filters.
    // Admins are excluded; capped at 50 so the picker stays fast.
    public function recipients(Request $request)
    {
        $query = User::query()
            ->select('id', 'name', 'email', 'role_id')
            ->with('role:id,name,slug')
            ->where('role_id', '!=', Role::ADMIN)
            ->orderBy('name');

        if ($request->filled('role')) {
            $roleId = $request->string('role')->toString() === 'seller'
                ? Role::SELLER
                : Role::USER;
            $query->where('role_id', $roleId);
        }

        if ($request->filled('search')) {
            $term = '%' . $request->string('search')->trim() . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('email', 'like', $term);
            });
        }

        return response()->json($query->limit(50)->get());
    }

    // Admin only — opens a NEW thread addressed to a chosen user/seller.
    // The user then sees it in Contact.vue's "Your messages" (it's scoped
    // by user_id, same as threads they started), and the admin keeps
    // replying through the existing reply() action.
    public function start(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'message' => 'required|string|min:2|max:3000',
        ]);

        $recipient = User::findOrFail($validated['user_id']);

        if ($recipient->role_id === Role::ADMIN) {
            return response()->json([
                'error' => 'You cannot start a conversation with another admin.',
            ], 422);
        }

        // forceFill: status/handled_by/initiated_by_admin are deliberately
        // not mass-assignable (see ContactMessage's note).
        $thread = new ContactMessage();
        $thread->forceFill([
            'user_id' => $recipient->id,
            'message' => $validated['message'],
            'initiated_by_admin' => true,
            'status' => 'replied',
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Message sent.',
            'contact_message' => $thread->load([
                'sender:id,name,email,role_id',
                'replies.user:id,name,role_id',
            ]),
        ], 201);
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
        try {
            return response()->json(
                ContactMessage::with([
                    'sender:id,name,email,role_id',
                    'replies.user:id,name,role_id',
                ])->findOrFail($id)
            );
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'error' => 'Contact message not found.',
            ], 404);
        }
    }

    // Admin only — mark as read without necessarily resolving it yet.
    public function markRead(Request $request, $id)
    {
        try {
            $message = ContactMessage::findOrFail($id);
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'error' => 'Contact message not found.',
            ], 404);
        }

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
        try {
            $message = ContactMessage::findOrFail($id);
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'error' => 'Contact message not found.',
            ], 404);
        }

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

        try {
            $message = ContactMessage::findOrFail($id);
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'error' => 'Contact message not found.',
            ], 404);
        }

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