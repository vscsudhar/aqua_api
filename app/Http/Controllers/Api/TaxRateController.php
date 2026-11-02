<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaxRateResource;
use App\Models\CompanySetting;
use App\Models\TaxRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxRateController extends Controller
{
    /**
     * Display a listing of tax rates.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TaxRate::query();

        if ($request->has('is_active') && $request->is_active !== '' && $request->is_active !== 'all') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('tax_type') && $request->tax_type !== 'all') {
            $query->where('tax_type', $request->tax_type);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $query->orderBy('rate', 'asc')->orderBy('name', 'asc');

        if ($request->boolean('all')) {
            $taxes = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => TaxRateResource::collection($taxes),
            ]);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => TaxRateResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Store a newly created tax rate.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_type' => ['sometimes', 'string', 'in:gst,vat,custom,exempt'],
            'applicability' => ['sometimes', 'string', 'in:all,goods,services'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if (! empty($validated['is_default'])) {
            TaxRate::where('is_default', true)->update(['is_default' => false]);
            CompanySetting::getSettings()->update(['default_tax_rate' => $validated['rate']]);
        }

        $taxRate = TaxRate::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Tax rate created successfully.',
            'data' => new TaxRateResource($taxRate),
        ], 201);
    }

    /**
     * Display the specified tax rate.
     */
    public function show(int $id): JsonResponse
    {
        $taxRate = TaxRate::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => new TaxRateResource($taxRate),
        ]);
    }

    /**
     * Update the specified tax rate.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $taxRate = TaxRate::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'tax_type' => ['sometimes', 'string', 'in:gst,vat,custom,exempt'],
            'applicability' => ['sometimes', 'string', 'in:all,goods,services'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if (! empty($validated['is_default'])) {
            TaxRate::where('id', '!=', $id)->where('is_default', true)->update(['is_default' => false]);
            $rateValue = $validated['rate'] ?? $taxRate->rate;
            CompanySetting::getSettings()->update(['default_tax_rate' => $rateValue]);
        }

        $taxRate->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Tax rate updated successfully.',
            'data' => new TaxRateResource($taxRate),
        ]);
    }

    /**
     * Toggle active/inactive status of a tax rate.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $taxRate = TaxRate::findOrFail($id);

        $newStatus = ! $taxRate->is_active;
        $updates = ['is_active' => $newStatus];

        if (! $newStatus && $taxRate->is_default) {
            $updates['is_default'] = false;
        }

        $taxRate->update($updates);

        return response()->json([
            'status' => 'success',
            'message' => $newStatus ? 'Tax rate activated.' : 'Tax rate deactivated.',
            'data' => new TaxRateResource($taxRate),
        ]);
    }

    /**
     * Remove the specified tax rate.
     * Deactivates safely if referenced in settings or products.
     */
    public function destroy(int $id): JsonResponse
    {
        $taxRate = TaxRate::findOrFail($id);

        if ($taxRate->is_default) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete the default tax rate. Set another tax as default first.',
            ], 422);
        }

        $taxRate->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tax rate deleted successfully.',
        ]);
    }
}
