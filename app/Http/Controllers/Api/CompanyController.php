<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    use ApiResponseTrait;

    /**
     * List all registered client companies.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Company::query();

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company_code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('gstin', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        $companies = $query->orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $companies,
            'count' => $companies->count(),
        ]);
    }

    /**
     * Onboard a new company.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_code' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'gstin' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive',
        ]);

        return DB::transaction(function () use ($validated) {
            $code = $validated['company_code'] ?? null;
            if (empty($code)) {
                // Generate next CMP-xxxx code
                $maxId = (Company::max('id') ?? 0) + 1;
                $code = 'CMP-' . str_pad((string) $maxId, 4, '0', STR_PAD_LEFT);
                while (Company::where('company_code', $code)->exists()) {
                    $maxId++;
                    $code = 'CMP-' . str_pad((string) $maxId, 4, '0', STR_PAD_LEFT);
                }
            } else {
                // Ensure uniqueness
                if (Company::where('company_code', $code)->exists()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'The company code already exists.',
                        'errors' => ['company_code' => ['The company code has already been taken.']],
                    ], 422);
                }
            }

            $company = Company::create([
                'company_code' => $code,
                'name' => trim($validated['name']),
                'contact_person' => $validated['contact_person'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'gstin' => !empty($validated['gstin']) ? strtoupper(trim($validated['gstin'])) : null,
                'status' => $validated['status'] ?? 'active',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Company onboarded successfully.',
                'data' => $company,
            ], 201);
        });
    }

    /**
     * Display the specified company.
     */
    public function show(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $company,
        ]);
    }

    /**
     * Update company details.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'gstin' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive',
        ]);

        if (isset($validated['name'])) {
            $company->name = trim($validated['name']);
        }
        if (array_key_exists('contact_person', $validated)) {
            $company->contact_person = $validated['contact_person'];
        }
        if (array_key_exists('phone', $validated)) {
            $company->phone = $validated['phone'];
        }
        if (array_key_exists('email', $validated)) {
            $company->email = $validated['email'];
        }
        if (array_key_exists('address', $validated)) {
            $company->address = $validated['address'];
        }
        if (array_key_exists('gstin', $validated)) {
            $company->gstin = !empty($validated['gstin']) ? strtoupper(trim($validated['gstin'])) : null;
        }
        if (array_key_exists('status', $validated)) {
            $company->status = $validated['status'];
        }

        $company->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Company updated successfully.',
            'data' => $company,
        ]);
    }
}
