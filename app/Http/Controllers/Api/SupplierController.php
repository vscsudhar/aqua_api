<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers with search and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query()->withCount('purchases')->orderBy('name');

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        // If requesting all for dropdown
        if ($request->query('all') === 'true') {
            $suppliers = $query->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Suppliers list retrieved.',
                'data' => SupplierResource::collection($suppliers),
            ]);
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $suppliers = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Suppliers retrieved successfully.',
            'data' => SupplierResource::collection($suppliers),
            'pagination' => [
                'current_page' => $suppliers->currentPage(),
                'last_page' => $suppliers->lastPage(),
                'per_page' => $suppliers->perPage(),
                'total' => $suppliers->total(),
            ],
        ]);
    }

    /**
     * Store a newly created supplier.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:suppliers,code'],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'gstin' => ['nullable', 'string', 'max:50'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['opening_balance'] = $validated['opening_balance'] ?? 0.00;
        $validated['current_balance'] = $validated['opening_balance'];
        $validated['created_by'] = $request->user()?->id;

        $supplier = Supplier::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier created successfully.',
            'data' => new SupplierResource($supplier),
        ], 201);
    }

    /**
     * Display the specified supplier.
     */
    public function show(int $id): JsonResponse
    {
        $supplier = Supplier::with(['purchases.items.product'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier details retrieved.',
            'data' => new SupplierResource($supplier),
        ]);
    }

    /**
     * Update the specified supplier.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('suppliers', 'code')->ignore($supplier->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'gstin' => ['nullable', 'string', 'max:50'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['updated_by'] = $request->user()?->id;
        $supplier->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier updated successfully.',
            'data' => new SupplierResource($supplier),
        ]);
    }

    /**
     * Remove the specified supplier (soft delete).
     */
    public function destroy(int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        if ($supplier->isReferencedInTransactions()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete supplier because purchase transactions reference this supplier. Deactivate instead.',
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier deleted successfully.',
        ]);
    }
}
