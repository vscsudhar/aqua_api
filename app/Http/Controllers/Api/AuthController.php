<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * Authenticate user and issue Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $login = trim($request->input('login'));
        $password = $request->input('password');

        // Allow authentication via email or username across all users
        $user = User::withoutGlobalScopes()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $request->hitRateLimiter();

            return $this->errorResponse(
                'Invalid email/username or password.',
                401,
                ['credentials' => ['The provided credentials do not match our records.']]
            );
        }

        // Scenario 11 & Requirement: Inactive user cannot log in
        if (! $user->is_active) {
            return $this->errorResponse(
                'Your account has been deactivated. Please contact administration.',
                403
            );
        }

        // Scenario 11: Inactive company cannot log in
        if ($user->company_id) {
            $company = Company::find($user->company_id);
            if ($company && $company->status !== 'active') {
                return $this->errorResponse(
                    'Your company account is currently inactive. Please contact administration.',
                    403
                );
            }
        }

        $request->clearRateLimiter();

        $deviceName = $request->input('device_name') ?: 'MarinaAquaApp';
        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Logged in successfully.');
    }

    /**
     * Retrieve the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user()),
            'User profile retrieved successfully.'
        );
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(
            null,
            'Logged out successfully.'
        );
    }

    /**
     * Change password for the authenticated user.
     */
    public function updatePassword(ResetPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return $this->errorResponse(
                'The current password does not match.',
                422,
                ['current_password' => ['The provided current password is incorrect.']]
            );
        }

        $user->update([
            'password' => Hash::make($request->input('new_password')),
        ]);

        return $this->successResponse(
            null,
            'Password changed successfully.'
        );
    }
}