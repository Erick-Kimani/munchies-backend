<?php

namespace App\Http\Controllers;

use App\Models\PropertyEditRequest;
use App\Models\PropertySubmission;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyEditRequestController extends Controller
{
    // Admin only — the review queue. Defaults to pending (what the admin
    // actually needs to act on); ?status=approved|rejected|all for a
    // historical view. Eager-loads just enough of the submission and
    // requester to render a review row without a follow-up request per
    // item.
    public function index(Request $request)
    {
        $query = PropertyEditRequest::with([
            'submission:id,type,full_name,location,status,photo_path',
            'requester:id,name,email',
        ])->latest();

        $status = $request->query('status', 'pending');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json($query->get());
    }

    // Requires auth:sanctum — a seller proposing a change to one of their
    // own listings. This never touches the live property_submissions row
    // directly; it only ever creates a pending request. See approve()
    // for where a change actually takes effect.
    public function store(Request $request, $submissionId)
    {
        try {
            $submission = PropertySubmission::with('editRequests')->findOrFail($submissionId);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['error' => 'Property submission not found.'], 404);
        }

        // Ownership, not role — scoped to whoever actually submitted this
        // specific listing, same pattern as every other "...mine"-style
        // check in this app (ContactMessageController::mine, etc).
        if ((int) $submission->user_id !== (int) $request->user()->id) {
            return response()->json(['error' => 'You can only request edits to your own listings.'], 403);
        }

        if ($submission->status === 'rejected') {
            return response()->json(['error' => 'This listing was rejected and can no longer be edited.'], 422);
        }

        if ($submission->hasPendingEditRequest()) {
            return response()->json([
                'error' => 'You already have an edit request pending review for this listing. '
                    . 'Please wait for it to be reviewed before requesting another change.',
            ], 422);
        }

        if ($submission->edit_requests_remaining <= 0) {
            // Machine-readable flag so the frontend can switch to the
            // "email us with a substantive reason" prompt instead of a
            // generic error message.
            return response()->json([
                'error' => 'You have used both of your edit requests for this listing.',
                'slots_exhausted' => true,
            ], 422);
        }

        $validated = $request->validate([
            // Mirrors PropertySubmissionController::store's own rules for
            // these same fields, so a value that was acceptable at
            // submission time is also acceptable here.
            'type' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'latitude' => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude',
            'phone' => 'nullable|string|max:30',
            // Required on every request — gives the admin context to
            // judge it by, and reuses the same field name/UI the
            // slots-exhausted "email us" fallback asks for, so a seller
            // only ever learns one shape of "explain the change."
            'seller_note' => 'required|string|min:10|max:1000',
        ]);

        $changedFields = collect(['type', 'description', 'latitude', 'longitude', 'phone'])
            ->filter(fn ($field) => array_key_exists($field, $validated) && $validated[$field] !== null);

        if ($changedFields->isEmpty()) {
            return response()->json([
                'error' => 'Please change at least one field (property type, description, map position, or phone).',
            ], 422);
        }

        $editRequest = PropertyEditRequest::create([
            'property_submission_id' => $submission->id,
            'requested_by' => $request->user()->id,
            'type' => $validated['type'] ?? null,
            'description' => $validated['description'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'seller_note' => $validated['seller_note'],
        ]);

        return response()->json([
            'message' => 'Edit request submitted. An admin will review it shortly.',
            'edit_request' => $editRequest,
            'edit_requests_remaining' => max(
                0,
                PropertyEditRequest::MAX_PER_SUBMISSION - $submission->editRequests->count() - 1
            ),
        ], 201);
    }

    // Admin only — approves a pending edit request, applying exactly the
    // fields it proposed (and no others) onto the live listing. Wrapped
    // in a locked transaction so this can't race a concurrent
    // approve/reject of the same request (e.g. two admin tabs open).
    public function approve(Request $request, $id)
    {
        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        try {
            $result = DB::transaction(function () use ($id, $validated, $request) {
                $editRequest = PropertyEditRequest::with('submission')
                    ->lockForUpdate()
                    ->findOrFail($id);

                if ($editRequest->status !== PropertyEditRequest::STATUS_PENDING) {
                    abort(response()->json([
                        'error' => 'This edit request has already been reviewed.',
                    ], 422));
                }

                $submission = $editRequest->submission;

                foreach ($editRequest->changedFields() as $field) {
                    $submission->{$field} = $editRequest->{$field};
                }
                $submission->save();

                // Direct property assignment + save() — NOT ->update().
                // status/admin_note/reviewed_by/reviewed_at are
                // deliberately excluded from PropertyEditRequest::
                // $fillable (so a seller can never set them via their own
                // request body), which means ->update() would silently
                // drop every one of these keys instead of setting them.
                $editRequest->status = PropertyEditRequest::STATUS_APPROVED;
                $editRequest->admin_note = $validated['admin_note'] ?? null;
                $editRequest->reviewed_by = $request->user()->id;
                $editRequest->reviewed_at = now();
                $editRequest->save();

                return [$editRequest->fresh(), $submission->fresh()];
            });
        } catch (ModelNotFoundException $exception) {
            return response()->json(['error' => 'Edit request not found.'], 404);
        }

        [$editRequest, $submission] = $result;

        return response()->json([
            'message' => 'Edit request approved and applied to the listing.',
            'edit_request' => $editRequest,
            'submission' => $submission,
        ]);
    }

    // Admin only — rejects a pending edit request. The listing itself is
    // never touched; only the request's own status changes. admin_note is
    // required here specifically (optional on approve) because a
    // rejection is the one outcome the seller needs an explanation for —
    // "approved" is self-explanatory, "rejected" usually isn't.
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'admin_note' => 'required|string|min:5|max:1000',
        ]);

        try {
            $editRequest = PropertyEditRequest::lockForUpdate()->findOrFail($id);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['error' => 'Edit request not found.'], 404);
        }

        if ($editRequest->status !== PropertyEditRequest::STATUS_PENDING) {
            return response()->json(['error' => 'This edit request has already been reviewed.'], 422);
        }

        // Same reasoning as approve() above — direct assignment, not
        // ->update(), for the same guarded fields.
        $editRequest->status = PropertyEditRequest::STATUS_REJECTED;
        $editRequest->admin_note = $validated['admin_note'];
        $editRequest->reviewed_by = $request->user()->id;
        $editRequest->reviewed_at = now();
        $editRequest->save();

        return response()->json([
            'message' => 'Edit request rejected.',
            'edit_request' => $editRequest->fresh(),
        ]);
    }
}