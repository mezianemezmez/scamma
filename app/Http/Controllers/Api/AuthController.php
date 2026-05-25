<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login user with username and password
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $validated['username'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'username', 'email', 'created_at', 'updated_at'])
        ]);
    }

    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'username', 'email', 'created_at', 'updated_at'])
        ], 201);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->only(['id', 'name', 'username', 'email', 'created_at', 'updated_at'])
        );
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful'
        ], 200);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        // Revoke all tokens for the user
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices'
        ], 200);
    }

    /**
     * Refresh token (create new token and revoke old one)
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revoke current token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Token refreshed',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'username', 'email'])
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:255', 'unique:users,username,' . $user->getKey()],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->getKey()],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
        ]);

        $data = collect($validated)->except(['password', 'password_confirmation'])->toArray();
        
        if (isset($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()->only(['id', 'name', 'username', 'email', 'updated_at'])
        ]);
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8'],
            'new_password_confirmation' => ['required', 'string', 'same:new_password'],
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($validated['new_password'])
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
            'user' => $user->fresh()->only(['id', 'name', 'username', 'email', 'updated_at'])
        ]);
    }
}
