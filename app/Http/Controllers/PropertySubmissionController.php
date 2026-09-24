<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PropertySubmission;
use App\Models\TermsAcceptance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertySubmissionController extends Controller
{
    // Requires auth:sanctum — only logged-in users can submit a property.
    //
    // A property submission now requires a completed listing-fee payment
    // first (see MpesaPaymentController::initiate). The frontend runs the
    // STK Push to completion, then submits the form with the resulting
    // checkout_request_id. This endpoint re-verifies the payment itself
    // rather than trusting the frontend's word that payment succeeded —
    // "report only what the response proves" applies here to our own
    // payment state just as much as to a Daraja response.
    public function store(Request $request)
    {
        $validated = $request->validate([
            'checkout_request_id' => 'required|string',
            'type' => 'required|string|max:100',
            // Seller's intent — distinct from `type` above (the property
            // category). Determines whether this listing surfaces on the
            // Buy page ('sale') or the Rent page ('rent') once featured.
            'listing_type' => 'required|in:sale,rent',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'price_range' => 'required|string|max:100',
            'location' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            // Up to three photos — see PropertySubmission::$fillable /
            // getPhotoUrlsAttribute for how they're stored and served.
            // All optional so a listing without photos still submits.
            'photo' => 'nullable|image|max:5120', // 5MB
            'photo_2' => 'nullable|image|max:5120',
            'photo_3' => 'nullable|image|max:5120',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            // A declaration about THIS property — required on every
            // submission, not just once per account, same as the paid fee
            // itself. See TermsAcceptance::isCurrentVersion() below: a
            // stale frontend build sending an outdated version string is
            // refused rather than silently recorded as consent.
            'accepted_terms' => 'required|accepted',
            'accepted_terms_version' => 'required|string|max:40',
        ]);

        if (!TermsAcceptance::isCurrentVersion('seller', $validated['accepted_terms_version'])) {
            return response()->json([
                'message' => 'The seller terms have changed since you loaded this page. Please refresh and try again.',
            ], 409);
        }

        $user = $request->user();

        // Wrapped in a transaction with a row lock on the payment: two
        // concurrent submissions can't both consume the same completed
        // payment, even if they race each other right after payment
        // succeeds (e.g. a double form-submit).
        $submission = DB::transaction(function () use ($validated, $request, $user) {
            $payment = Payment::where('checkout_request_id', $validated['checkout_request_id'])
                ->where('user_id', $user->id)
                ->where('purpose', Payment::PURPOSE_PROPERTY_LISTING_FEE)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                abort(response()->json(['message' => 'No matching payment was found.'], 402));
            }

            if (!$payment->isCompleted()) {
                abort(response()->json(['message' => 'The listing fee payment has not completed yet.'], 402));
            }

            if ($payment->isConsumed()) {
                abort(response()->json(['message' => 'This payment has already been used for a submission.'], 402));
            }

            $submissionData = collect($validated)
                ->except(['checkout_request_id', 'photo', 'photo_2', 'photo_3', 'accepted_terms', 'accepted_terms_version'])
                ->all();

            if ($request->hasFile('photo')) {
                $submissionData['photo_path'] = $request->file('photo')->store('property-submissions', 'public');
            }

            if ($request->hasFile('photo_2')) {
                $submissionData['photo_path_2'] = $request->file('photo_2')->store('property-submissions', 'public');
            }

            if ($request->hasFile('photo_3')) {
                $submissionData['photo_path_3'] = $request->file('photo_3')->store('property-submissions', 'public');
            }

            // Behind auth:sanctum this is always the logged-in user — never
            // trusted for status or review fields, only for attribution.
            $submissionData['user_id'] = $user->id;
            $submissionData['payment_id'] = $payment->id;

            $submission = PropertySubmission::create($submissionData);

            // Recorded in the same transaction as the submission itself,
            // and linked to it — see terms_acceptances.property_submission_id.
            // A seller declaration should never exist without the listing
            // it was made about, or vice versa.
            TermsAcceptance::record(
                $user->id,
                'seller',
                $validated['accepted_terms_version'],
                TermsAcceptance::CONTEXT_PROPERTY_SUBMISSION,
                $request,
                $submission->id
            );

            $payment->consumed_at = now();
            $payment->save();

            return $submission;
        });

        // Submitting a property is what makes someone a Seller. Promote a
        // plain User (role_id 3) to Seller (role_id 2) the first time they
        // submit. Deliberately guarded to role_id === 3 only, so this can
        // never touch an Admin (role_id 1) or re-run on an existing Seller.
        if ($user->role_id === 3) {
            $user->role_id = 2;
            $user->save();
        }

        return response()->json([
            'message' => 'Submission received. Our team will review it shortly.',
            'submission' => $submission,
            'user' => $user->fresh(),
        ], 201);
    }

    // PUBLIC — powers the Buy / Rent pages. Only ever returns featured
    // submissions; pending and rejected ones are never exposed publicly.
    //
    // Guests (no valid Sanctum token) get the phone masked and the email
    // stripped out entirely — not just hidden by the frontend, actually
    // absent from this response, since a masked value or omitted field
    // is the only real way to keep it out of the browser's Network tab.
    // See PropertyEnquiryModal.vue for the matching display logic.
    public function featured(Request $request)
    {
        $query = PropertySubmission::query()
            ->where('status', 'featured')
            ->latest();

        // listing_type ('sale' | 'rent') is what actually separates the Buy
        // page from the Rent page. `type` (category) is an optional
        // additional filter on top of that — the two are independent axes,
        // never conflated. See Buypage.vue / Rentpage.vue on the frontend.
        if ($request->filled('listing_type')) {
            $request->validate(['listing_type' => 'in:sale,rent']);
            $query->where('listing_type', $request->string('listing_type'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        // 'sanctum' explicitly here (rather than auth:sanctum middleware
        // on the route, which would 401 guests outright) — this route
        // needs to stay reachable without a token AND still recognise a
        // valid one when present, so we resolve the guard directly and
        // treat null as "guest" rather than blocking the request.
        $isAuthenticated = (bool) $request->user('sanctum');

        $results = $query->get()->map(function (PropertySubmission $submission) use ($isAuthenticated) {
            $data = $submission->toArray();

            if (!$isAuthenticated) {
                $data['phone'] = self::maskPhone($submission->phone);
                unset($data['email']);
            }

            return $data;
        });

        return response()->json($results);
    }

    // Masks all but the leading digits of a phone number, e.g.
    // "+254791018109" -> "+2547910*****". Used only for guests — see
    // featured() above.
    private static function maskPhone(?string $phone): ?string
    {
        if (!$phone) {
            return $phone;
        }

        $maskedLength = min(5, strlen($phone));
        $visibleLength = strlen($phone) - $maskedLength;

        return substr($phone, 0, $visibleLength) . str_repeat('*', $maskedLength);
    }

    // Admin only — list submissions, optionally filtered by status and/or
    // listing_type (sale/rent). Both filters are optional and independent.
    public function index(Request $request)
    {
        $query = PropertySubmission::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('listing_type')) {
            $request->validate(['listing_type' => 'in:sale,rent']);
            $query->where('listing_type', $request->string('listing_type'));
        }

        return response()->json($query->paginate(20));
    }

    // Admin only — view a single submission.
    public function show($id)
    {
        return response()->json(PropertySubmission::findOrFail($id));
    }

    // Admin only — "Feature" button. Publishes the submission to Buy/Rent.
    //
    // NOTE: status/reviewed_by/reviewed_at/review_note are deliberately
    // excluded from PropertySubmission::$fillable (see the model) so they
    // can never be set from a submitter's request payload. That means
    // mass-assignment helpers (update()/fill()/create()) silently DROP
    // those keys instead of throwing — so we set them via direct property
    // assignment + save() here, which bypasses the guard on purpose.
    public function feature(Request $request, $id)
    {
        $submission = PropertySubmission::findOrFail($id);

        $submission->status = 'featured';
        // Starts the one-month feature window. UnfeatureExpiredListings
        // (scheduled hourly, see routes/console.php) automatically pulls
        // this back to 'pending' one calendar month from this timestamp.
        $submission->featured_at = now();
        $submission->reviewed_by = $request->user()->id;
        $submission->reviewed_at = now();
        $submission->review_note = $request->input('review_note');
        $submission->save();

        return response()->json([
            'message' => 'Submission featured.',
            'submission' => $submission->fresh(),
        ]);
    }

    // Admin only — "Unfeature" button. Pulls it back off Buy/Rent, back
    // into the pending queue for re-review. Also invoked automatically
    // by the UnfeatureExpiredListings scheduled command once a listing's
    // month is up.
    public function unfeature(Request $request, $id)
    {
        $submission = PropertySubmission::findOrFail($id);

        $submission->status = 'pending';
        $submission->featured_at = null;
        $submission->reviewed_by = $request->user()->id;
        $submission->reviewed_at = now();
        $submission->save();

        return response()->json([
            'message' => 'Submission moved back to pending.',
            'submission' => $submission->fresh(),
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

        $submission->status = 'rejected';
        $submission->featured_at = null;
        $submission->reviewed_by = $request->user()->id;
        $submission->reviewed_at = now();
        $submission->review_note = $request->input('review_note');
        $submission->save();

        return response()->json([
            'message' => 'Submission rejected.',
            'submission' => $submission->fresh(),
        ]);
    }
}