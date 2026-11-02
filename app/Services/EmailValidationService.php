<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class EmailValidationService
{
    /**
     * Strict duplicate email check.
     * One email must belong to only one company.
     */
    public static function validateUniqueEmail(string $email, ?int $targetCompanyId = null, ?int $ignoreUserId = null): void
    {
        $normalizedEmail = strtolower(trim($email));

        $query = User::withoutGlobalScopes()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail]);

        if ($ignoreUserId) {
            $query->where('id', '!=', $ignoreUserId);
        }

        $existingUser = $query->first();

        if ($existingUser) {
            if ($existingUser->company_id && $targetCompanyId && (int) $existingUser->company_id === (int) $targetCompanyId) {
                throw ValidationException::withMessages([
                    'email' => ['Already registered in this company. Delete the existing user before registering again.']
                ]);
            } else {
                throw ValidationException::withMessages([
                    'email' => ['This email is already registered under another company. Please contact the respective company administrator to delete the existing account before registering again.']
                ]);
            }
        }
    }
}