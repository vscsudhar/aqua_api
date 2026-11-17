<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordResetController extends Controller
{
    use ApiResponseTrait;

    /**
     * Request a password reset code for an email or username.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string',
        ]);

        $login = trim($validated['email']);

        $user = User::withoutGlobalScopes()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (! $user) {
            return $this->errorResponse(
                'No registered user account found with that email or username.',
                404,
                ['email' => ['No account found matching this identifier.']]
            );
        }

        if (! $user->is_active) {
            return $this->errorResponse(
                'Your account has been deactivated. Please contact administration.',
                403
            );
        }

        // Generate 6-digit reset code
        $code = (string) mt_rand(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset verification code generated successfully.',
            'data' => [
                'email' => $user->email,
                'reset_code' => $code,
                'expires_in_minutes' => 60,
            ],
            'errors' => null,
        ]);
    }

    /**
     * Reset the user password using the verification code.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'code' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $email = strtolower(trim($validated['email']));
        $code = trim($validated['code']);

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $record) {
            return $this->errorResponse(
                'Invalid or expired password reset request. Please request a new code.',
                422,
                ['code' => ['No active reset request found for this email.']]
            );
        }

        // Check 60-minute expiration
        if ($record->created_at && Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return $this->errorResponse(
                'The verification code has expired. Please request a new code.',
                422,
                ['code' => ['The verification code has expired.']]
            );
        }

        if (! Hash::check($code, $record->token)) {
            return $this->errorResponse(
                'The verification code provided is incorrect.',
                422,
                ['code' => ['Invalid verification code.']]
            );
        }

        $user = User::withoutGlobalScopes()->where('email', $email)->first();

        if (! $user) {
            return $this->errorResponse('User not found.', 404);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        // Clear used reset code
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Invalidate active personal access tokens for safety
        $user->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been reset successfully. Please log in with your new password.',
            'data' => [
                'email' => $user->email,
            ],
            'errors' => null,
        ]);
    }
}
