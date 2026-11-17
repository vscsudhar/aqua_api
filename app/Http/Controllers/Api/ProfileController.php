<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            'profile_image' => 'nullable|string',
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

        if (array_key_exists('profile_image', $validated)) {
            $user->profile_image = !empty($validated['profile_image']) ? trim($validated['profile_image']) : null;
        }

        $user->save();

        return $this->successResponse(
            new UserResource($user->fresh()),
            'Profile updated successfully.'
        );
    }

    /**
     * Change password for the authenticated user.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'nullable|string',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->errorResponse(
                'The current password does not match.',
                422,
                ['current_password' => ['The provided current password is incorrect.']]
            );
        }

        if (Hash::check($validated['new_password'], $user->password)) {
            return $this->errorResponse(
                'The new password cannot be the same as your current password.',
                422,
                ['new_password' => ['New password must be different from current password.']]
            );
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return $this->successResponse(
            null,
            'Password changed successfully.'
        );
    }

    /**
     * Upload or update profile image for the authenticated user.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        // Support multipart file upload
        if ($request->hasFile('image') || $request->hasFile('profile_image')) {
            $file = $request->file('image') ?: $request->file('profile_image');

            $request->validate([
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            ]);

            // Remove old local image if exists
            if ($user->profile_image && ! str_starts_with($user->profile_image, 'http') && ! str_starts_with($user->profile_image, 'data:')) {
                if (Storage::disk('public')->exists($user->profile_image)) {
                    Storage::disk('public')->delete($user->profile_image);
                }
            }

            $extension = $file->getClientOriginalExtension() ?: 'png';
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $extension;
            $path = $file->storeAs('avatars', $filename, 'public');

            $user->profile_image = $path;
            $user->save();

            return $this->successResponse(
                new UserResource($user->fresh()),
                'Profile image uploaded successfully.'
            );
        }

        // Support base64 or image URL
        $validated = $request->validate([
            'image_data' => 'required|string',
        ]);

        $imageData = trim($validated['image_data']);
        $user->profile_image = $imageData;
        $user->save();

        return $this->successResponse(
            new UserResource($user->fresh()),
            'Profile image updated successfully.'
        );
    }

    /**
     * Delete profile image for the authenticated user.
     */
    public function deleteImage(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if ($user->profile_image && ! str_starts_with($user->profile_image, 'http') && ! str_starts_with($user->profile_image, 'data:')) {
            if (Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }
        }

        $user->profile_image = null;
        $user->save();

        return $this->successResponse(
            new UserResource($user->fresh()),
            'Profile image removed successfully.'
        );
    }
}
