<?php

namespace App\Http\Controllers;

use App\Models\TermsAcceptance;
use Illuminate\Http\Request;

class TermsController extends Controller
{
    // PUBLIC — GET /terms/current
    //
    // Which version of each document is currently in force. The frontend
    // doesn't strictly need this to function (src/data/terms.js already
    // carries the version it expects to send), but it's a cheap way for
    // it to notice at boot that it's shipped a stale build, and it's what
    // /terms/status compares a user's acceptances against.
    public function current()
    {
        $documents = collect(config('terms.documents'))->map(fn ($doc, $audience) => [
            'audience' => $audience,
            'version' => $doc['version'],
            'label' => $doc['label'],
        ])->values();

        return response()->json(['documents' => $documents]);
    }

    // Requires auth:sanctum — GET /terms/status
    //
    // For each configured audience, the user's most recent acceptance (if
    // any) and whether it matches the current version. Lets the frontend
    // decide whether to prompt a re-acceptance after we publish new
    // wording, without the user needing to hit register or submit again
    // to find out.
    public function status(Request $request)
    {
        $user = $request->user();
        $audiences = array_keys(config('terms.documents'));

        $latestByAudience = TermsAcceptance::where('user_id', $user->id)
            ->whereIn('audience', $audiences)
            ->orderByDesc('accepted_at')
            ->get()
            ->groupBy('audience')
            ->map(fn ($rows) => $rows->first());

        $status = collect($audiences)->mapWithKeys(function ($audience) use ($latestByAudience) {
            $latest = $latestByAudience->get($audience);

            return [$audience => [
                'version' => $latest?->version,
                'accepted_at' => $latest?->accepted_at,
                'is_current' => $latest
                    ? TermsAcceptance::isCurrentVersion($audience, $latest->version)
                    : false,
            ]];
        });

        return response()->json($status);
    }

    // Requires auth:sanctum — POST /terms/accept
    //
    // Standalone acceptance, for whenever the acceptance isn't already
    // riding along with a bigger request (registration, a property
    // submission) — e.g. a "we've updated our terms, please re-accept"
    // prompt shown to an existing user on next login. Rejects a version
    // that isn't current: this endpoint is for accepting what's live now,
    // not for re-writing history with an old version string.
    public function accept(Request $request)
    {
        $validated = $request->validate([
            'audience' => 'required|string|in:' . implode(',', array_keys(config('terms.documents'))),
            'version' => 'required|string|max:40',
            'context' => 'nullable|string|max:40',
        ]);

        if (!TermsAcceptance::isCurrentVersion($validated['audience'], $validated['version'])) {
            return response()->json([
                'message' => 'That is not the current version of these terms. Please reload and try again.',
            ], 409);
        }

        $acceptance = TermsAcceptance::record(
            $request->user()->id,
            $validated['audience'],
            $validated['version'],
            $validated['context'] ?? TermsAcceptance::CONTEXT_RE_ACCEPTANCE,
            $request
        );

        return response()->json([
            'message' => 'Thanks — recorded.',
            'acceptance' => $acceptance,
        ], 201);
    }
}