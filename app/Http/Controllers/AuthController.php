<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        // This user is choosing their own password right now, so treat it
        // the same as having already used /set-password — it keeps that
        // endpoint's one-time rule meaningful for manually-registered
        // accounts too, not just ones that started out on Google.
        $validated['password_set_at'] = now();

        // Role is never trusted from the client. Every public sign-up
        // becomes a plain "User" (role_id 3). Admins/Sellers must be
        // promoted separately (e.g. by an admin, or a dedicated
        // "become a seller" flow), never chosen at registration time.
        $validated['role_id'] = 3;

        try {
            $user = User::create($validated);

            return response()->json([
                'message' => 'Registration successful. Please log in.',
                'user' => $user,
            ], 201);

        } catch (\Exception $exception) {
            return response()->json([
                'error' => 'Registration failed.',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'error' => 'The provided credentials are incorrect.'
            ], 401);
        }

        $token = $user->createToken('auth-token', ['*'], now()->addDay())->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user,
            'token' => $token,
            'abilities' => $user->abilities(),
        ]);
    }

    /**
     * Logs in with a Google OAuth access_token obtained client-side via
     * Google Identity Services (see the frontend's services/googleAuth.js).
     * Silently registers the user on first sign-in.
     *
     * We never trust the token at face value — every field we act on
     * (email, sub, email_verified) comes back from Google's own servers
     * in response to this request, not from anything the client sent us.
     */
    public function googleAuth(Request $request)
    {
        $validated = $request->validate([
            'access_token' => 'required|string',
        ]);

        // Ask Google who this token belongs to. A forged or expired token
        // fails right here with a non-200, before we trust anything.
        $userInfoResponse = Http::withToken($validated['access_token'])
            ->get('https://www.googleapis.com/oauth2/v3/userinfo');

        if ($userInfoResponse->failed()) {
            return response()->json([
                'error' => 'Invalid or expired Google token.',
            ], 401);
        }

        $googleUser = $userInfoResponse->json();

        // Cross-check the token's audience against our own client ID, so a
        // valid access token minted for some *other* Google app can't be
        // replayed here.
        $tokenInfoResponse = Http::get('https://www.googleapis.com/oauth2/v3/tokeninfo', [
            'access_token' => $validated['access_token'],
        ]);

        $expectedClientId = config('services.google.client_id');

        if (
            $tokenInfoResponse->failed()
            || ! $expectedClientId
            || $tokenInfoResponse->json('aud') !== $expectedClientId
        ) {
            return response()->json([
                'error' => 'Google token was not issued for this application.',
            ], 401);
        }

        if (empty($googleUser['sub']) || empty($googleUser['email'])) {
            return response()->json([
                'error' => 'Google did not return the expected account details.',
            ], 422);
        }

        if (! ($googleUser['email_verified'] ?? false)) {
            return response()->json([
                'error' => 'Your Google account email is not verified.',
            ], 422);
        }

        // Look up by google_id first (returning user), then by email (an
        // existing password account signing in with Google for the first
        // time — link it rather than creating a duplicate), else create.
        $user = User::where('google_id', $googleUser['sub'])->first();

        if (! $user) {
            $user = User::where('email', $googleUser['email'])->first();

            if ($user) {
                $user->update([
                    'google_id' => $googleUser['sub'],
                    'avatar' => $googleUser['picture'] ?? $user->avatar,
                ]);
            }
        }

        if (! $user) {
            $user = User::create([
                'name' => $googleUser['name'] ?? $googleUser['email'],
                'email' => $googleUser['email'],
                // Random, never-revealed hash — this account can only ever
                // be reached via Google sign-in unless the user later goes
                // through "forgot password" to set a real one.
                'password' => Hash::make(Str::random(40)),
                'role_id' => 3,
                'google_id' => $googleUser['sub'],
                'avatar' => $googleUser['picture'] ?? null,
            ]);
        }

        $token = $user->createToken('auth-token', ['*'], now()->addDay())->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user,
            'token' => $token,
            'abilities' => $user->abilities(),
        ]);
    }

    /**
     * Lets an already-authenticated user (e.g. one who only ever signed in
     * via Google and has a random, never-revealed password hash) set a
     * real password of their own choosing. Because this route sits behind
     * auth:sanctum, $request->user() is resolved securely from their
     * token — never from anything the client claims in the request body —
     * so a user can only ever set a password on their own account.
     *
     * No email/reset-code step is needed here, unlike forgotPassword(),
     * because holding a valid Sanctum token already proves ownership of
     * the account.
     *
     * This is a ONE-TIME action. Once password_set_at is recorded, this
     * endpoint refuses further attempts — any future change to the
     * password is expected to go through forgot-password/reset-password
     * instead. An admin can grant a single further attempt via
     * grantSetPasswordAccess() for a user who gets locked out (e.g. lost
     * access to the email their forgot-password code would go to).
     */
    public function setPassword(Request $request)
    {
        $user = $request->user();

        if (! $user->can_set_password) {
            return response()->json([
                'error' => 'You have already set a password for this account. Use "Forgot password" to change it.',
            ], 403);
        }

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'password_set_at' => now(),
            // Consume the override (if any) now that it's been used —
            // it's single-use even if it was never actually needed.
            'password_set_override' => false,
        ]);

        return response()->json([
            'message' => 'Password set successfully. You can now log in manually too.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Admin-only: grant one specific user a single further attempt at
     * POST /set-password, even though they've already used it once. Meant
     * for a user who's locked out of manual login and can't complete the
     * email-based forgot-password flow either (e.g. no access to that
     * inbox anymore). The grant is consumed automatically the next time
     * this user successfully sets a password.
     */
    public function grantSetPasswordAccess(Request $request, $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'error' => 'User not found.',
            ], 404);
        }

        $user->update(['password_set_override' => true]);

        return response()->json([
            'message' => "{$user->name} can now use the Set password page one more time.",
            'user' => $user->fresh(),
        ]);
    }

    public function logout(Request $request)
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logout successful.'
        ]);
    }

    /**
     * Request a password reset code. Always responds the same way whether
     * or not the email exists, so this endpoint can't be used to check
     * which emails are registered.
     */
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Don't reveal whether the email exists — same response either way.
        if (! $user) {
            return response()->json([
                'message' => 'If that email is registered, a reset code has been sent.',
            ]);
        }

        $resetCode = (string) random_int(100000, 999999);

        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($resetCode),
                'created_at' => now(),
            ]
        );

                \Mail::send('emails.password-reset', [
            'user' => $user,
            'code' => $resetCode,
        ], function ($message) use ($user) {
            $message->to($user->email)->subject('Reset your Tawi Properties password');
        });

        return response()->json([
            'message' => 'If that email is registered, a reset code has been sent.',
        ]);
    }

    /**
     * Verify the emailed code and set a new password. Revokes all of the
     * user's existing Sanctum tokens on success, so a compromised session
     * doesn't survive a password reset.
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $resetRecord = \DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (! $resetRecord || ! Hash::check($validated['code'], $resetRecord->token)) {
            return response()->json([
                'error' => 'Invalid or expired reset code.',
            ], 422);
        }

        if (now()->diffInMinutes($resetRecord->created_at) > 30) {
            \DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

            return response()->json([
                'error' => 'Reset code has expired. Please request a new one.',
            ], 422);
        }

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'error' => 'Invalid or expired reset code.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
            // A Google-only user can reach a real password this way too,
            // bypassing /set-password entirely (e.g. they still have email
            // access but not the account's Google login). Record it the
            // same way, and clear any pending admin override — it was
            // meant for /set-password specifically and is moot now that
            // they've already set a password by another route.
            'password_set_at' => $user->password_set_at ?? now(),
            'password_set_override' => false,
        ]);

        \DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        // Password reset should invalidate any existing sessions/tokens.
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully. Please log in with your new password.',
        ]);
    }

    /**
     * Admin-only: look a user up by email. Exists so an admin handling a
     * "I'm locked out" support request can find the account's id (needed
     * by grantSetPasswordAccess) from just the email address they were
     * given, without a full user-management UI.
     */
    public function findUserByEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'error' => 'No user found with that email.',
            ], 404);
        }

        return response()->json($user);
    }

    public function getUserById(Request $request, $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'error' => 'User not found.'
            ], 404);
        }

        return response()->json($user);
    }

    public function deleteUser(Request $request, $id)
    {
        $currentUser = $request->user();

        if (! $currentUser || ! $currentUser->isAdmin()) {
            return response()->json([
                'error' => 'Unauthorized. Only administrators can delete users.'
            ], 403);
        }

        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'error' => 'User not found.'
            ], 404);
        }

        if ($user->id === $currentUser->id) {
            return response()->json([
                'error' => 'Administrators cannot delete their own account through this endpoint.'
            ], 400);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.'
        ]);
    }
}