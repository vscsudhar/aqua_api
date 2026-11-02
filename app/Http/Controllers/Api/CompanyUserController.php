<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\EmailValidationService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CompanyUserController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get authenticated company's ID.
     */
    protected function getCompanyId(): int
    {
        $user = request()->user() ?: Auth::user() ?: Auth::guard('sanctum')->user();
        if (! $user || empty($user->company_id)) {
            abort(403, 'User does not belong to a valid company.');
        }

        return (int) $user->company_id;
    }

    /**
     * List company users with search and pagination/all.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $company = Auth::user()->company;
        $maxUsers = $company?->plan?->max_users ?? 5;

        $query = User::withoutGlobalScopes()->where('company_id', $companyId);

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role') && $request->query('role') !== 'all') {
            $query->where('role', $request->query('role'));
        }

        if ($request->has('is_active') && $request->query('is_active') !== '') {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->orderBy('id', 'desc')->get();
        $currentUsersCount = User::withoutGlobalScopes()->where('company_id', $companyId)->count();

        return $this->successResponse([
            'users' => UserResource::collection($users),
            'plan_usage' => [
                'plan_name' => $company?->plan?->name ?? 'Standard Plan',
                'max_users' => $maxUsers,
                'current_users' => $currentUsersCount,
                'remaining_slots' => max(0, $maxUsers - $currentUsersCount),
                'is_limit_reached' => $currentUsersCount >= $maxUsers,
                'expires_at' => $company?->expires_at?->format('Y-m-d'),
                'duration' => $company?->plan?->duration ?? '30 Days',
            ],
        ], 'Company staff users retrieved successfully.');
    }

    /**
     * Create staff user for the authenticated company.
     * Requirement 4:
     * - Inherit Company ID automatically
     * - Respect plan max user limit
     * - Strict duplicate email check (Requirement 5)
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $company = Auth::user()->company;
        $maxUsers = $company?->plan?->max_users ?? 5;

        // 1. Enforce Plan User Limit (Requirement 6)
        $currentUsersCount = User::withoutGlobalScopes()->where('company_id', $companyId)->count();
        if ($currentUsersCount >= $maxUsers) {
            return $this->errorResponse(
                "Plan user limit reached. Your plan allows a maximum of {$maxUsers} users. Please contact system administration to upgrade your plan.",
                422
            );
        }

        // 2. Validate input
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,manager,accountant,sales_rep',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        // 3. Strict duplicate email validation (Requirement 5)
        EmailValidationService::validateUniqueEmail($request->input('email'), $companyId);

        // 4. Create user - company_id is ALWAYS enforced from authenticated session
        $user = User::create([
            'company_id' => $companyId,
            'name' => trim($request->input('name')),
            'email' => strtolower(trim($request->input('email'))),
            'username' => strtolower(trim($request->input('email'))),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
            'role' => $request->input('role'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->successResponse(
            new UserResource($user),
            'Staff user created successfully.',
            201
        );
    }

    /**
     * Show single user details.
     */
    public function show(int $id): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $user = User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return $this->successResponse(new UserResource($user), 'User retrieved successfully.');
    }

    /**
     * Update user details.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $user = User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'role' => 'required|in:admin,manager,accountant,sales_rep',
            'phone' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:6',
            'is_active' => 'nullable|boolean',
        ]);

        // Duplicate email check
        EmailValidationService::validateUniqueEmail($request->input('email'), $companyId, $user->id);

        $data = [
            'name' => trim($request->input('name')),
            'email' => strtolower(trim($request->input('email'))),
            'phone' => $request->input('phone'),
            'role' => $request->input('role'),
        ];

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->update($data);

        return $this->successResponse(new UserResource($user), 'User updated successfully.');
    }

    /**
     * Toggle user active/inactive status.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $user = User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        if ($user->id === Auth::id()) {
            return $this->errorResponse('You cannot deactivate your own account.', 422);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $statusStr = $user->is_active ? 'activated' : 'deactivated';

        return $this->successResponse(new UserResource($user), "User {$statusStr} successfully.");
    }

    /**
     * Delete user strictly within company.
     */
    public function destroy(int $id): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $user = User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        if ($user->id === Auth::id()) {
            return $this->errorResponse('You cannot delete your own account.', 422);
        }

        $user->delete();

        return $this->successResponse(null, 'User deleted successfully.');
    }
}