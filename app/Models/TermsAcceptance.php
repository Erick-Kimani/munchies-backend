<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermsAcceptance extends Model
{
    use HasFactory;

    // Matches the keys in config('terms.documents'). Plain constants
    // rather than a DB-backed enum, same reasoning as Payment::PURPOSE_*.
    public const AUDIENCE_GENERAL = 'general';
    public const AUDIENCE_SELLER = 'seller';

    public const CONTEXT_REGISTRATION = 'registration';
    public const CONTEXT_PROPERTY_SUBMISSION = 'property_submission';
    public const CONTEXT_RE_ACCEPTANCE = 're_acceptance';

    protected $fillable = [
        'user_id',
        'audience',
        'version',
        'context',
        'property_submission_id',
        'ip_address',
        'user_agent',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function propertySubmission()
    {
        return $this->belongsTo(PropertySubmission::class);
    }

    /**
     * Record an acceptance from the current request. Centralized here so
     * every call site (register, googleAuth, property submission, the
     * standalone /terms/accept endpoint) captures the same fields the
     * same way — the IP/user-agent capture in particular is easy to
     * forget on any one of them individually.
     */
    public static function record(
        int $userId,
        string $audience,
        string $version,
        ?string $context,
        ?\Illuminate\Http\Request $request = null,
        ?int $propertySubmissionId = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'audience' => $audience,
            'version' => $version,
            'context' => $context,
            'property_submission_id' => $propertySubmissionId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 1000) : null,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Whether the version string a client just sent actually matches the
     * current version configured server-side for this audience. Both
     * `register()` and `PropertySubmissionController::store()` use this
     * rather than just checking `accepted_terms === true`, so a stale
     * frontend build (or a hand-crafted request) can't push through an
     * acceptance of a document version nobody currently sees.
     */
    public static function isCurrentVersion(string $audience, ?string $version): bool
    {
        $current = config("terms.documents.{$audience}.version");

        return $current !== null && $version === $current;
    }
}