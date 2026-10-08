<?php

namespace App\Http\Controllers;

use App\Models\PropertyEditRequest;
use App\Models\PropertySubmission;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PropertyEditRequestController extends Controller
{
    // Ceiling on admin-granted extra slots per listing.
    private const MAX_EXTRA_GRANTED = 10;

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
                'error' => 'You have used all of your edit requests for this listing.',
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
            // Replacement photos, same rules as the original submission.
            // Sent as multipart; each one replaces the matching slot.
            'photo' => 'nullable|image|max:5120',
            'photo_2' => 'nullable|image|max:5120',
            'photo_3' => 'nullable|image|max:5120',
            // Required on every request — gives the admin context to
            // judge it by, and reuses the same field name/UI the
            // slots-exhausted "email us" fallback asks for, so a seller
            // only ever learns one shape of "explain the change."
            'seller_note' => 'required|string|min:10|max:1000',
        ]);

        $changedFields = collect(['type', 'description', 'latitude', 'longitude', 'phone'])
            ->filter(fn ($field) => array_key_exists($field, $validated) && $validated[$field] !== null);

        $photoInputs = ['photo' => 'photo_path', 'photo_2' => 'photo_path_2', 'photo_3' => 'photo_path_3'];
        $uploadedInputs = collect($photoInputs)->filter(fn ($column, $input) => $request->hasFile($input));

        if ($changedFields->isEmpty() && $uploadedInputs->isEmpty()) {
            return response()->json([
                'error' => 'Please change at least one field (property type, description, map position, phone, or photos).',
            ], 422);
        }

        // Stored only after every check above has passed, so a rejected
        // request never leaves orphaned files behind.
        $photoPaths = [];
        foreach ($uploadedInputs as $input => $column) {
            $photoPaths[$column] = $request->file($input)->store('property-edit-requests', 'public');
        }

        $editRequest = PropertyEditRequest::create([
            'property_submission_id' => $submission->id,
            'requested_by' => $request->user()->id,
            'type' => $validated['type'] ?? null,
            'description' => $validated['description'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'photo_path' => $photoPaths['photo_path'] ?? null,
            'photo_path_2' => $photoPaths['photo_path_2'] ?? null,
            'photo_path_3' => $photoPaths['photo_path_3'] ?? null,
            'seller_note' => $validated['seller_note'],
        ]);

        return response()->json([
            'message' => 'Edit request submitted. An admin will review it shortly.',
            'edit_request' => $editRequest,
            // $submission->editRequests was loaded before this request was
            // created, so remaining is still the pre-create figure.
            'edit_requests_remaining' => max(0, $submission->edit_requests_remaining - 1),
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

                // Photos being replaced: remember the old files so they
                // can be deleted once the new ones are safely saved.
                $replacedPhotos = [];

                foreach ($editRequest->changedFields() as $field) {
                    if (in_array($field, PropertyEditRequest::PHOTO_COLUMNS, true) && $submission->{$field}) {
                        $replacedPhotos[] = $submission->{$field};
                    }
                    $submission->{$field} = $editRequest->{$field};
                }
                $submission->save();

                foreach ($replacedPhotos as $oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }

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

        // The proposed photos were never used, so remove them from disk.
        foreach (PropertyEditRequest::PHOTO_COLUMNS as $column) {
            if ($editRequest->{$column}) {
                Storage::disk('public')->delete($editRequest->{$column});
            }
        }

        return response()->json([
            'message' => 'Edit request rejected.',
            'edit_request' => $editRequest->fresh(),
        ]);
    }

    // Admin only — grants one listing extra edit-request slots on top of
    // the standard cap, for a seller who's used theirs up but has a
    // genuine reason to change something again. Additive (count defaults
    // to 1) and bounded by MAX_EXTRA_GRANTED so it can't be run away.
    public function grantExtra(Request $request, $submissionId)
    {
        $validated = $request->validate([
            'count' => 'nullable|integer|min:1|max:5',
        ]);
        $count = (int) ($validated['count'] ?? 1);

        $submission = PropertySubmission::find($submissionId);

        if (! $submission) {
            return response()->json(['error' => 'Property submission not found.'], 404);
        }

        $granted = DB::transaction(function () use ($submission, $count) {
            $locked = PropertySubmission::whereKey($submission->id)->lockForUpdate()->first();

            if ($locked->extra_edit_requests + $count > self::MAX_EXTRA_GRANTED) {
                return null;
            }

            $locked->increment('extra_edit_requests', $count);

            return $locked->fresh()->loadCount('editRequests');
        });

        if (! $granted) {
            return response()->json([
                'error' => 'This listing already has the maximum of ' . self::MAX_EXTRA_GRANTED
                    . ' extra edit requests granted.',
            ], 422);
        }

        $remaining = $granted->edit_requests_remaining;

        return response()->json([
            'message' => "Granted {$count} extra edit request" . ($count === 1 ? '' : 's')
                . ". This listing now has {$remaining} remaining.",
            'edit_requests_remaining' => $remaining,
            'edit_requests_limit' => $granted->edit_requests_limit,
        ]);
    }
}
