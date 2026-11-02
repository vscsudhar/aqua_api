<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Plan;
use App\Models\User;
use App\Services\EmailValidationService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    use ApiResponseTrait;

    /**
     * Super Admin login.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = trim($request->input('login'));
        $password = $request->input('password');

        $user = User::withoutGlobalScopes()
            ->where(function ($q) use ($login) {
                $q->where('email', $login)->orWhere('username', $login);
            })
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return $this->errorResponse(
                'Invalid credentials.',
                401,
                ['credentials' => ['The provided credentials do not match our records.']]
            );
        }

        if (! $user->isSuperAdmin()) {
            return $this->errorResponse(
                'Access denied. Only Super Administrators can log into the Main Admin Panel.',
                403
            );
        }

        if (! $user->is_active) {
            return $this->errorResponse(
                'Your account has been deactivated.',
                403
            );
        }

        $token = $user->createToken('SuperAdminPanel')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Super Admin authenticated successfully.');
    }

    /**
     * Super Admin Dashboard metrics.
     */
    public function dashboard(): JsonResponse
    {
        $totalCompanies = Company::count();
        $activeCompanies = Company::where('status', 'active')->count();
        $inactiveCompanies = Company::where('status', 'inactive')->count();
        $totalUsers = User::withoutGlobalScopes()->where('role', '!=', 'super_admin')->count();
        $activePlans = Plan::where('status', 'active')->count();

        $recentCompanies = Company::with(['plan', 'adminUser'])
            ->withCount('users')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return $this->successResponse([
            'total_companies' => $totalCompanies,
            'active_companies' => $activeCompanies,
            'inactive_companies' => $inactiveCompanies,
            'total_registered_users' => $totalUsers,
            'active_plans' => $activePlans,
            'recent_companies' => $recentCompanies,
        ], 'Dashboard metrics retrieved successfully.');
    }

    /**
     * List all companies with filtering and search.
     */
    public function companiesIndex(Request $request): JsonResponse
    {
        $query = Company::with(['plan', 'adminUser'])->withCount('users');

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('adminUser', function ($adminQ) use ($search) {
                        $adminQ->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->query('plan_id'));
        }

        $companies = $query->orderBy('id', 'desc')->get();

        return $this->successResponse($companies, 'Companies retrieved successfully.');
    }

    /**
     * Create a new company and its admin account.
     * Requirement 2:
     * - Generate unique Company ID
     * - Create company admin account
     * - Assign admin to newly created company
     * - Assign selected plan
     * - Generate login credentials
     * - Display credentials to Super Admin with copy option
     * - Passwords securely hashed
     */
    public function companiesStore(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|string|min:6',
            'plan_id' => 'required|exists:plans,id',
            'status' => 'nullable|in:active,inactive',
            'admin_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        // Duplicate email validation (Requirement 5)
        EmailValidationService::validateUniqueEmail($request->input('admin_email'));

        $plan = Plan::findOrFail($request->input('plan_id'));
        $status = $request->input('status', 'active');
        $adminPassword = $request->input('admin_password');

        $result = DB::transaction(function () use ($request, $plan, $status, $adminPassword) {
            // Generate unique Company ID
            $nextId = (Company::max('id') ?? 0) + 1;
            $companyCode = 'CMP-' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);

            // Double check uniqueness
            while (Company::where('company_code', $companyCode)->exists()) {
                $nextId++;
                $companyCode = 'CMP-' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
            }

            $duration = $plan->duration ?? '30 Days';
            $days = 30;
            if (preg_match('/(\d+)\s*day/i', $duration, $m)) {
                $days = (int) $m[1];
            } elseif (preg_match('/(\d+)\s*year/i', $duration, $m)) {
                $days = (int) $m[1] * 365;
            } elseif (preg_match('/(\d+)\s*month/i', $duration, $m)) {
                $days = (int) $m[1] * 30;
            }
            $expiresAt = now()->addDays($days)->toDateString();

            // 1. Create company record
            $company = Company::create([
                'company_code' => $companyCode,
                'name' => trim($request->input('name')),
                'plan_id' => $plan->id,
                'expires_at' => $expiresAt,
                'status' => $status,
                'email' => strtolower(trim($request->input('admin_email'))),
                'phone' => $request->input('phone'),
                'address' => $request->input('address'),
            ]);

            // 2. Create company admin user
            $adminName = trim($request->input('admin_name') ?: $company->name . ' Admin');
            $adminUser = User::create([
                'company_id' => $company->id,
                'name' => $adminName,
                'email' => strtolower(trim($request->input('admin_email'))),
                'username' => strtolower(Str::slug($company->name)) . '_admin',
                'phone' => $request->input('phone'),
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'is_active' => $status === 'active',
            ]);

            // 3. Initialize default company setting for this new company
            CompanySetting::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'company_name' => $company->name,
                'tagline' => 'Packaged Drinking Water & Supply',
                'phone' => $company->phone ?: '+91 90000 00000',
                'email' => $company->email,
                'address' => $company->address ?: 'Company Address',
                'state_name' => 'Tamil Nadu',
                'state_code' => '33',
                'currency_symbol' => '₹',
                'currency_code' => 'INR',
                'default_tax_rate' => 18.00,
                'invoice_prefix' => 'INV',
                'invoice_number_padding' => 4,
                'next_invoice_number' => 1,
            ]);

            // 4. Initialize default measurement units
            $defaultUnits = [
                ['name' => 'Litre', 'code' => 'LTR', 'description' => 'Volume in litres for bottled water and 20L cans'],
                ['name' => 'Millilitre', 'code' => 'ML', 'description' => 'Volume in millilitres (200ml, 500ml)'],
                ['name' => 'Numbers', 'code' => 'NOS', 'description' => 'Individual piece count or count of cans'],
                ['name' => 'Kilogram', 'code' => 'KG', 'description' => 'Mass in kilograms for bulk plastics, resin, scrap'],
                ['name' => 'Pack', 'code' => 'PACK', 'description' => 'Shrink-wrapped bottle package (e.g. 12 pack, 24 pack)'],
                ['name' => 'Can', 'code' => 'CAN', 'description' => '20L bubble water top cans'],
                ['name' => 'Box', 'code' => 'BOX', 'description' => 'Corrugated boxes for bottled water shipping'],
            ];
            $now = now();
            DB::table('units')->insert(array_map(function ($u) use ($company, $now) {
                return array_merge($u, [
                    'company_id' => $company->id,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }, $defaultUnits));

            return [
                'company' => $company->load(['plan', 'adminUser']),
                'credentials' => [
                    'company_name' => $company->name,
                    'company_id' => $company->company_code,
                    'admin_user_id' => $adminUser->id,
                    'admin_email' => $adminUser->email,
                    'temporary_password' => $adminPassword,
                    'assigned_plan' => $plan->name,
                    'max_users' => $plan->max_users,
                    'account_status' => $company->status,
                ],
            ];
        });

        return $this->successResponse($result, 'Company and Admin account created successfully.', 201);
    }

    /**
     * Show single company details.
     */
    public function companiesShow(int $id): JsonResponse
    {
        $company = Company::with(['plan', 'adminUser', 'users'])->withCount('users')->findOrFail($id);

        return $this->successResponse($company, 'Company details retrieved successfully.');
    }

    /**
     * Update company details.
     */
    public function companiesUpdate(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'plan_id' => 'required|exists:plans,id',
            'status' => 'required|in:active,inactive',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        $updateData = [
            'name' => trim($request->input('name')),
            'plan_id' => $request->input('plan_id'),
            'status' => $request->input('status'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
        ];

        if ($company->plan_id != $request->input('plan_id')) {
            $newPlan = Plan::find($request->input('plan_id'));
            $duration = $newPlan?->duration ?? '30 Days';
            $days = 30;
            if (preg_match('/(\d+)\s*day/i', $duration, $m)) {
                $days = (int) $m[1];
            } elseif (preg_match('/(\d+)\s*year/i', $duration, $m)) {
                $days = (int) $m[1] * 365;
            } elseif (preg_match('/(\d+)\s*month/i', $duration, $m)) {
                $days = (int) $m[1] * 30;
            }
            $updateData['expires_at'] = now()->addDays($days)->toDateString();
        }

        $company->update($updateData);

        return $this->successResponse($company->load(['plan', 'adminUser']), 'Company updated successfully.');
    }

    /**
     * Toggle company active status.
     */
    public function companiesToggleStatus(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $company->status = $company->status === 'active' ? 'inactive' : 'active';
        $company->save();

        return $this->successResponse($company, "Company marked as {$company->status}.");
    }

    /**
     * Delete company with permanent cascading removal of all associated data.
     */
    public function companiesDestroy(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $companyName = $company->name;
        $companyCode = $company->company_code;

        DB::transaction(function () use ($company) {
            $companyId = $company->id;

            // 1. Get user IDs to delete tokens
            $userIds = User::withoutGlobalScopes()->where('company_id', $companyId)->pluck('id');
            if ($userIds->isNotEmpty()) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $userIds)
                    ->delete();
            }

            // 2. Cascade delete all tenant data
            $tenantTables = [
                'company_settings',
                'invoice_payments',
                'invoice_items',
                'credit_notes',
                'invoices',
                'dealer_ledger_entries',
                'dealer_supplies',
                'dealer_supply_items',
                'dealer_payments',
                'dealer_product_prices',
                'dealers',
                'daily_other_transactions',
                'stock_adjustments',
                'inventory_movements',
                'purchase_items',
                'purchases',
                'products',
                'product_categories',
                'units',
                'tax_rates',
                'suppliers',
            ];

            foreach ($tenantTables as $tableName) {
                if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'company_id')) {
                    DB::table($tableName)->where('company_id', $companyId)->delete();
                }
            }

            // 3. Delete company users
            User::withoutGlobalScopes()->where('company_id', $companyId)->delete();

            // 4. Delete company
            $company->delete();
        });

        return $this->successResponse(
            null,
            "Company '{$companyName}' ({$companyCode}) and all its associated database records were permanently deleted."
        );
    }

    /**
     * List company users and check plan usage.
     */
    public function companyUsersIndex(int $id): JsonResponse
    {
        $company = Company::with(['plan'])->findOrFail($id);
        $users = User::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('id', 'desc')
            ->get();

        $maxUsers = (int) ($company->plan?->max_users ?? 5);
        $currentUsersCount = $users->count();

        return $this->successResponse([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'company_code' => $company->company_code,
                'plan_name' => $company->plan?->name ?? 'Standard Plan',
                'max_users' => $maxUsers,
                'current_users' => $currentUsersCount,
                'remaining_slots' => max(0, $maxUsers - $currentUsersCount),
                'is_limit_reached' => $currentUsersCount >= $maxUsers,
            ],
            'users' => UserResource::collection($users),
        ], 'Company users retrieved successfully.');
    }

    /**
     * Create user against company respecting plan limit.
     * This user can log into the marina_aqua application.
     */
    public function companyUsersStore(Request $request, int $id): JsonResponse
    {
        $company = Company::with('plan')->findOrFail($id);
        $maxUsers = (int) ($company->plan?->max_users ?? 5);

        $currentUsersCount = User::withoutGlobalScopes()->where('company_id', $company->id)->count();
        if ($currentUsersCount >= $maxUsers) {
            return $this->errorResponse(
                "Plan user limit reached. This company's plan allows a maximum of {$maxUsers} users. Please upgrade the subscription plan to add more users.",
                422
            );
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
            'role' => 'nullable|string|in:admin,manager,accountant,sales_rep,staff,operator',
            'phone' => 'nullable|string|max:50',
            'username' => 'nullable|string|max:100',
        ]);

        EmailValidationService::validateUniqueEmail($request->input('email'));

        $email = strtolower(trim($request->input('email')));
        $username = trim($request->input('username') ?: explode('@', $email)[0] . '_' . rand(100, 999));

        $roleInput = strtolower(trim((string) $request->input('role', 'sales_rep')));
        if ($roleInput === 'staff' || $roleInput === 'operator' || empty($roleInput)) {
            $roleInput = 'sales_rep';
        }

        $user = User::create([
            'company_id' => $company->id,
            'name' => trim($request->input('name')),
            'email' => $email,
            'username' => $username,
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
            'role' => $roleInput,
            'is_active' => true,
        ]);

        return $this->successResponse([
            'user' => new UserResource($user),
            'credentials' => [
                'company_name' => $company->name,
                'company_id' => $company->company_code,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'password' => $request->input('password'),
                'role' => $user->role,
                'assigned_plan' => $company->plan?->name ?? 'Standard',
            ],
            'remaining_slots' => max(0, $maxUsers - ($currentUsersCount + 1)),
        ], 'User created successfully for company. User can log into the Marina Aqua application with these credentials.', 201);
    }

    /**
     * Update company user details, email, and password.
     */
    public function companyUsersUpdate(Request $request, int $id, int $userId): JsonResponse
    {
        $company = Company::findOrFail($id);
        $user = User::withoutGlobalScopes()->where('company_id', $company->id)->findOrFail($userId);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:6',
            'role' => 'nullable|string|in:admin,manager,accountant,sales_rep,staff,operator',
            'phone' => 'nullable|string|max:50',
            'username' => 'nullable|string|max:100',
        ]);

        if ($request->filled('name')) {
            $user->name = trim($request->input('name'));
        }

        if ($request->filled('email')) {
            $newEmail = strtolower(trim($request->input('email')));
            if ($newEmail !== $user->email) {
                EmailValidationService::validateUniqueEmail($newEmail, $company->id, $user->id);
                $user->email = $newEmail;
            }
        }

        $passwordChanged = false;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
            $passwordChanged = true;
            // Revoke active personal access tokens so the user logs in with new password
            $user->tokens()->delete();
        }

        if ($request->filled('role')) {
            $roleInput = strtolower(trim((string) $request->input('role')));
            if ($roleInput === 'staff' || $roleInput === 'operator' || empty($roleInput)) {
                $roleInput = 'sales_rep';
            }
            $user->role = $roleInput;
        }

        if ($request->has('phone')) {
            $user->phone = $request->input('phone');
        }

        if ($request->filled('username')) {
            $user->username = trim($request->input('username'));
        }

        $user->save();

        $credentials = null;
        if ($passwordChanged) {
            $credentials = [
                'company_name' => $company->name,
                'company_code' => $company->company_code,
                'company_id' => $company->company_code,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'password' => $request->input('password'),
                'role' => $user->role,
                'assigned_plan' => $company->plan?->name ?? 'Standard',
            ];
        }

        return $this->successResponse([
            'user' => new UserResource($user),
            'credentials' => $credentials,
            'password_changed' => $passwordChanged,
        ], 'User updated successfully.');
    }

    /**
     * Toggle company user active status.
     */
    public function companyUsersToggleStatus(int $id, int $userId): JsonResponse
    {
        $user = User::withoutGlobalScopes()->where('company_id', $id)->findOrFail($userId);
        $user->is_active = !$user->is_active;
        $user->save();

        return $this->successResponse(new UserResource($user), "User status updated to " . ($user->is_active ? 'active' : 'inactive') . ".");
    }

    /**
     * Delete company user (cannot delete primary admin).
     */
    public function companyUsersDestroy(int $id, int $userId): JsonResponse
    {
        $user = User::withoutGlobalScopes()->where('company_id', $id)->findOrFail($userId);
        if ($user->role === 'admin') {
            return $this->errorResponse('Cannot delete the primary company admin account.', 422);
        }
        $user->delete();

        return $this->successResponse(null, 'User deleted successfully.');
    }

    /**
     * Plan Management: List plans.
     */
    public function plansIndex(): JsonResponse
    {
        $plans = Plan::withCount('companies')->orderBy('id', 'asc')->get();

        return $this->successResponse($plans, 'Plans retrieved successfully.');
    }

    /**
     * Plan Management: Create plan.
     */
    public function plansStore(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|string|max:50',
            'max_users' => 'required|integer|min:1',
            'status' => 'nullable|in:active,inactive',
        ]);

        $plan = Plan::create([
            'name' => trim($request->input('name')),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'duration' => trim($request->input('duration')),
            'max_users' => $request->input('max_users'),
            'status' => $request->input('status', 'active'),
        ]);

        return $this->successResponse($plan, 'Plan created successfully.', 201);
    }

    /**
     * Plan Management: Update plan.
     */
    public function plansUpdate(Request $request, int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|string|max:50',
            'max_users' => 'required|integer|min:1',
            'status' => 'required|in:active,inactive',
        ]);

        $plan->update([
            'name' => trim($request->input('name')),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'duration' => trim($request->input('duration')),
            'max_users' => $request->input('max_users'),
            'status' => $request->input('status'),
        ]);

        return $this->successResponse($plan, 'Plan updated successfully.');
    }

    /**
     * Plan Management: Toggle plan status.
     */
    public function plansToggleStatus(int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        $plan->status = $plan->status === 'active' ? 'inactive' : 'active';
        $plan->save();

        return $this->successResponse($plan, "Plan marked as {$plan->status}.");
    }

    /**
     * Plan Management: Delete plan.
     */
    public function plansDestroy(int $id): JsonResponse
    {
        $plan = Plan::withCount('companies')->findOrFail($id);

        if ($plan->companies_count > 0) {
            return $this->errorResponse(
                'Cannot delete plan because it is currently assigned to active companies. Deactivate it instead.',
                422
            );
        }

        $plan->delete();

        return $this->successResponse(null, 'Plan deleted successfully.');
    }
}