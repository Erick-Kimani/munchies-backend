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
            'role_id' => 'required|integer|exists:roles,id'
        ]);

        $validated['password'] = Hash::make($validated['password']);

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