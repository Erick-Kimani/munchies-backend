<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            $message->to($user->email)->subject('Reset Your Password');
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

        $user->update(['password' => Hash::make($validated['password'])]);

        \DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        // Password reset should invalidate any existing sessions/tokens.
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully. Please log in with your new password.',
        ]);
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