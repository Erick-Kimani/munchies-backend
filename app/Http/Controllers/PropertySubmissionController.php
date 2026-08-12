<?php

namespace App\Http\Controllers;

use App\Models\PropertySubmission;
use Illuminate\Http\Request;

class PropertySubmissionController extends Controller
{
    // Requires auth:sanctum — only logged-in users can submit a property.
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:100',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'price_range' => 'required|string|max:100',
            'location' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|max:5120', // 5MB
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('property-submissions', 'public');
        }

        // Behind auth:sanctum this is always the logged-in user — never
        // trusted for status or review fields, only for attribution.
        $validated['user_id'] = $request->user()->id;

        $submission = PropertySubmission::create($validated);

        return response()->json([
            'message' => 'Submission received. Our team will review it shortly.',
            'submission' => $submission,
        ], 201);
    }

    // PUBLIC — powers the Buy / Rent pages. Only ever returns featured
    // submissions; pending and rejected ones are never exposed publicly.
    public function featured(Request $request)
    {
        $query = PropertySubmission::query()
            ->where('status', 'featured')
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return response()->json($query->get());
    }

    // Admin only — list submissions, optionally filtered by status.
    public function index(Request $request)
    {
        $query = PropertySubmission::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->paginate(20));
    }

    // Admin only — view a single submission.
    public function show($id)
    {
        return response()->json(PropertySubmission::findOrFail($id));
    }

    // Admin only — "Feature" button. Publishes the submission to Buy/Rent.
    public function feature(Request $request, $id)
    {
        $submission = PropertySubmission::findOrFail($id);

        $submission->update([
            'status' => 'featured',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->input('review_note'),
        ]);

        return response()->json([
            'message' => 'Submission featured.',
            'submission' => $submission,
        ]);
    }

    // Admin only — "Unfeature" button. Pulls it back off Buy/Rent, back
    // into the pending queue for re-review.
    public function unfeature(Request $request, $id)
    {
        $submission = PropertySubmission::findOrFail($id);

        $submission->update([
            'status' => 'pending',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Submission moved back to pending.',
            'submission' => $submission,
        ]);
    }

    // Admin only — "Delete" button. Doesn't remove the row (kept for
    // record-keeping) — marks it rejected so it drops out of Buy/Rent
    // and the pending queue.
    public function reject(Request $request, $id)
    {
        $request->validate([
            'review_note' => 'nullable|string|max:1000',
        ]);

        $submission = PropertySubmission::findOrFail($id);

        $submission->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->input('review_note'),
        ]);

        return response()->json([
            'message' => 'Submission rejected.',
            'submission' => $submission,
        ]);
    }
}