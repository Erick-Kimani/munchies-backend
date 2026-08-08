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
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|integer|exists:roles,id'
        ]);

        $validated['password'] = Hash::make($validated['password']);

        try {
            $user = User::create($validated);

            return response()->json([
                'message' => 'Registration Successful!',
                'user' => $user
            ], 201);

        } catch (\Exception $exception) {
            return response()->json([
                'Error' => 'Registration failed.',
                'Message' => $exception->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required'
        ]);

        try {
            $user = User::where('email', $validated['email'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Login Successful!',
                'user' => $user,
                'token' => $token,
                'abilities' => $user->abilities()
            ]);

        } catch (\Exception $exception) {
            return response()->json([
                'Error' => 'Invalid Credentials.'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Log Out Successful.'
        ]);
    }
}
