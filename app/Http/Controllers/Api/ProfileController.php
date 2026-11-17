<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ApiResponseTrait;

    /**
     * Fetch the authenticated user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        return $this->successResponse(
            new UserResource($user),
            'User profile retrieved successfully.'
        );
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => 'nullable|string|max:50',
            'username' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
        ]);

        // Only update permitted fields - never allow role, password, company_id, or is_active
        $user->name = trim($validated['name']);
        $user->email = strtolower(trim($validated['email']));

        if (array_key_exists('phone', $validated)) {
            $user->phone = !empty($validated['phone']) ? trim($validated['phone']) : null;
        }

        if (array_key_exists('username', $validated)) {
            $user->username = !empty($validated['username']) ? trim($validated['username']) : null;
        }

        $user->save();

        return $this->successResponse(
            new UserResource($user->fresh()),
            'Profile updated successfully.'
        );
    }
}
