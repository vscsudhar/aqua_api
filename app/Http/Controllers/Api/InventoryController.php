<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryMovementResource;
use App\Http\Resources\StockAdjustmentResource;
use App\Models\InventoryMovement;
use App\Models\StockAdjustment;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Inventory dashboard summary (current stock, received, issued, low stock alerts).
     */
    public function summary(): JsonResponse
    {
        $summary = $this->inventoryService->getInventorySummary();

        return response()->json([
            'status' => 'success',
            'message' => 'Inventory summary metrics retrieved.',
            'data' => $summary,
        ]);
    }

    /**
     * Inventory ledger movements history.
     */
    public function movements(Request $request): JsonResponse
    {
        $query = InventoryMovement::with(['product', 'creator'])
            ->orderBy('movement_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->has('product_id') && ! empty($request->query('product_id'))) {
            $query->where('product_id', $request->query('product_id'));
        }

        if ($request->has('movement_type') && ! empty($request->query('movement_type'))) {
            $query->where('movement_type', $request->query('movement_type'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('movement_date', [
                $request->query('start_date'),
                $request->query('end_date'),
            ]);
        }

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('movement_no', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $movements = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Inventory movements ledger retrieved.',
            'data' => InventoryMovementResource::collection($movements),
            'pagination' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    /**
     * List stock adjustments history.
     */
    public function adjustmentsIndex(Request $request): JsonResponse
    {
        $query = StockAdjustment::with(['product', 'authorizedUser', 'creator'])
            ->orderBy('adjustment_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->has('product_id') && ! empty($request->query('product_id'))) {
            $query->where('product_id', $request->query('product_id'));
        }

        if ($request->has('adjustment_type') && ! empty($request->query('adjustment_type'))) {
            $query->where('adjustment_type', $request->query('adjustment_type'));
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $adjustments = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Stock adjustments list retrieved.',
            'data' => StockAdjustmentResource::collection($adjustments),
            'pagination' => [
                'current_page' => $adjustments->currentPage(),
                'last_page' => $adjustments->lastPage(),
                'per_page' => $adjustments->perPage(),
                'total' => $adjustments->total(),
            ],
        ]);
    }

    /**
     * Create an authorized stock adjustment.
     */
    public function adjustmentsStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'adjustment_type' => ['required', 'string', 'in:addition,reduction,damage,leakage,audit_reconciliation,expiry,loss'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'adjustment_date' => ['nullable', 'date'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
            'authorized_by' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $adjustment = $this->inventoryService->createStockAdjustment($validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Stock adjustment recorded and inventory updated.',
            'data' => new StockAdjustmentResource($adjustment),
        ], 201);
    }
}
