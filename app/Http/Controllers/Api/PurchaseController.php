<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Display a listing of purchases with supplier filter, date filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Purchase::with(['supplier', 'items.product', 'creator'])
            ->orderBy('purchase_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->has('supplier_id') && ! empty($request->query('supplier_id'))) {
            $query->where('supplier_id', $request->query('supplier_id'));
        }

        if ($request->has('status') && ! empty($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('purchase_date', [
                $request->query('start_date'),
                $request->query('end_date'),
            ]);
        } elseif ($request->has('date')) {
            $query->whereDate('purchase_date', $request->query('date'));
        }

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('purchase_no', 'like', "%{$search}%")
                    ->orWhere('invoice_no', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $purchases = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Purchases retrieved successfully.',
            'data' => PurchaseResource::collection($purchases),
            'pagination' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'per_page' => $purchases->perPage(),
                'total' => $purchases->total(),
            ],
        ]);
    }

    /**
     * Post a new purchase transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'invoice_no' => ['nullable', 'string', 'max:100'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['nullable', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $purchase = $this->inventoryService->postPurchase($validated, $request->user());
        $isNew = $purchase->wasRecentlyCreated;

        return response()->json([
            'status' => 'success',
            'message' => $isNew ? 'Purchase recorded and stock posted successfully.' : 'Existing purchase retrieved (idempotent).',
            'data' => new PurchaseResource($purchase),
        ], $isNew ? 201 : 200);
    }

    /**
     * Display purchase transaction details including stock movements.
     */
    public function show(int $id): JsonResponse
    {
        $purchase = Purchase::with(['supplier', 'items.product', 'movements', 'creator', 'updater'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase details retrieved.',
            'data' => new PurchaseResource($purchase),
        ]);
    }

    /**
     * Edit a posted purchase with controlled stock adjustments.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $purchase = Purchase::findOrFail($id);

        if ($purchase->status === 'cancelled') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot edit a cancelled or reversed purchase transaction.',
            ], 422);
        }

        $validated = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'invoice_no' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['nullable', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $this->inventoryService->updatePurchase($purchase, $validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase and inventory adjustments updated successfully.',
            'data' => new PurchaseResource($updated),
        ]);
    }

    /**
     * Reverse a posted purchase and safely reduce stock for tracked items.
     */
    public function reverse(Request $request, int $id): JsonResponse
    {
        $purchase = Purchase::findOrFail($id);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $reversed = $this->inventoryService->reversePurchase(
            $purchase,
            $request->input('reason', 'Purchase reversal requested'),
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase reversed and stock deducted successfully.',
            'data' => new PurchaseResource($reversed),
        ]);
    }

    /**
     * Delete purchase (soft delete) if allowed.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $purchase = Purchase::findOrFail($id);

        if ($purchase->status !== 'cancelled') {
            // First reverse the stock
            $this->inventoryService->reversePurchase($purchase, 'Deleted by user', $request->user());
        }

        $purchase->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase deleted and stock adjusted.',
        ]);
    }
}
