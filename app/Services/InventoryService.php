<?php

namespace App\Services;

use App\Models\DealerSupply;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Record an atomic inventory movement and update current_stock if product is stock-tracked.
     * Returns null if product has track_stock = false.
     */
    public function recordMovement(
        Product|int $productOrId,
        string $movementType,
        float $quantity,
        float $signedQuantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceNo = null,
        float $unitCost = 0.00,
        ?string $notes = null,
        ?int $userId = null,
        ?string $movementDate = null
    ): ?InventoryMovement {
        $productId = $productOrId instanceof Product ? $productOrId->id : $productOrId;

        // Lock product row
        $product = Product::where('id', $productId)->lockForUpdate()->first();
        if (! $product) {
            return null;
        }

        // Only stock-tracked products generate movements and modify current_stock
        if (! $product->track_stock) {
            return null;
        }

        $date = ! empty($movementDate)
            ? Carbon::parse($movementDate, 'Asia/Kolkata')->toDateString()
            : Carbon::today('Asia/Kolkata')->toDateString();

        $companyId = $product->company_id ?? Auth::user()?->company_id;
        $dateSlug = Carbon::parse($date)->format('Ymd');
        $movQuery = InventoryMovement::withoutGlobalScopes();
        if (method_exists(InventoryMovement::class, 'withTrashed')) {
            $movQuery = InventoryMovement::withoutGlobalScopes()->withTrashed();
        }
        if ($companyId) {
            $movQuery->where('company_id', $companyId);
        }
        $count = (clone $movQuery)->whereDate('movement_date', $date)->count() + 1;
        do {
            $movementNo = sprintf('MOV-%s-%04d', $dateSlug, $count);
            $count++;
        } while ((clone $movQuery)->where('movement_no', $movementNo)->exists());

        $balanceBefore = (float) $product->current_stock;
        $balanceAfter = round($balanceBefore + $signedQuantity, 2);

        $product->current_stock = $balanceAfter;
        $product->save();

        $totalCost = round(abs($quantity) * $unitCost, 2);

        return InventoryMovement::create([
            'company_id' => $companyId,
            'movement_no' => $movementNo,
            'movement_date' => $date,
            'product_id' => $product->id,
            'movement_type' => $movementType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_no' => $referenceNo,
            'quantity' => abs($quantity),
            'signed_quantity' => $signedQuantity,
            'unit' => $product->unit,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'notes' => $notes,
            'created_by' => $userId,
        ]);
    }

    /**
     * Post a new purchase, save line items with cost snapshots, and increase stock for tracked products.
     */
    public function postPurchase(array $data, ?User $user = null): Purchase
    {
        // 1. Idempotency Check
        if (! empty($data['idempotency_key'])) {
            $existing = Purchase::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing->load(['supplier', 'items.product', 'movements']);
            }
        }

        return DB::transaction(function () use ($data, $user) {
            $purchaseDate = ! empty($data['purchase_date'])
                ? Carbon::parse($data['purchase_date'], 'Asia/Kolkata')->toDateString()
                : Carbon::today('Asia/Kolkata')->toDateString();

            // Resolve supplier
            $supplier = null;
            $supplierName = $data['supplier_name'] ?? 'General Supplier';
            if (! empty($data['supplier_id'])) {
                $supplier = Supplier::find($data['supplier_id']);
                if ($supplier) {
                    $supplierName = $supplier->name;
                }
            }

            $companyId = $supplier?->company_id ?? $user?->company_id ?? Auth::user()?->company_id;
            // Generate Purchase No
            $dateSlug = Carbon::parse($purchaseDate)->format('Ymd');
            $purQuery = Purchase::withoutGlobalScopes()->withTrashed();
            if ($companyId) {
                $purQuery->where('company_id', $companyId);
            }
            $count = (clone $purQuery)->whereDate('purchase_date', $purchaseDate)->count() + 1;
            do {
                $purchaseNo = sprintf('PUR-%s-%04d', $dateSlug, $count);
                $count++;
            } while ((clone $purQuery)->where('purchase_no', $purchaseNo)->exists());

            $discountAmount = isset($data['discount_amount']) ? (float) $data['discount_amount'] : 0.00;
            $paidAmount = isset($data['paid_amount']) ? (float) $data['paid_amount'] : 0.00;

            $purchase = Purchase::create([
                'company_id' => $companyId,
                'purchase_no' => $purchaseNo,
                'supplier_id' => $supplier?->id,
                'supplier_name' => $supplierName,
                'invoice_no' => $data['invoice_no'] ?? ($data['reference_no'] ?? null),
                'purchase_date' => $purchaseDate,
                'subtotal' => 0.00,
                'tax_amount' => 0.00,
                'discount_amount' => $discountAmount,
                'total_amount' => 0.00,
                'paid_amount' => $paidAmount,
                'payment_status' => $paidAmount > 0 ? 'paid' : 'unpaid',
                'payment_method' => $data['payment_method'] ?? 'cash',
                'status' => 'received',
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            $subtotal = 0.00;
            $taxTotal = 0.00;

            if (empty($data['items']) || ! is_array($data['items'])) {
                throw ValidationException::withMessages([
                    'items' => ['At least one product item is required for a purchase.'],
                ]);
            }

            foreach ($data['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $qty = (float) $itemData['quantity'];
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Quantity for product '{$product->name}' must be greater than zero."],
                    ]);
                }

                $unitCost = (float) $itemData['unit_cost'];
                $taxRate = isset($itemData['tax_rate']) ? (float) $itemData['tax_rate'] : (float) ($product->tax_rate ?? 0.00);
                $itemDiscount = isset($itemData['discount_amount']) ? (float) $itemData['discount_amount'] : 0.00;

                $lineBase = round($qty * $unitCost, 2);
                $lineTax = round($lineBase * ($taxRate / 100), 2);
                $lineTotal = round($lineBase + $lineTax - $itemDiscount, 2);

                $subtotal += $lineBase;
                $taxTotal += $lineTax;

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $qty,
                    'unit' => $itemData['unit'] ?? $product->unit,
                    'unit_cost' => $unitCost,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $lineTax,
                    'discount_amount' => $itemDiscount,
                    'total_cost' => $lineTotal,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                // Increase stock for stock-tracked products
                if ($product->track_stock) {
                    $this->recordMovement(
                        productOrId: $product,
                        movementType: 'purchase_in',
                        quantity: $qty,
                        signedQuantity: $qty,
                        referenceType: 'purchase',
                        referenceId: $purchase->id,
                        referenceNo: $purchase->purchase_no,
                        unitCost: $unitCost,
                        notes: "Received via Purchase {$purchase->purchase_no}",
                        userId: $user?->id,
                        movementDate: $purchaseDate
                    );

                    // Separate purchase cost from dealer selling price
                    // We update the master cost_price without touching default_price
                    $product->cost_price = $unitCost;
                    $product->save();
                }
            }

            $totalAmount = round($subtotal + $taxTotal - $discountAmount, 2);
            $purchase->subtotal = $subtotal;
            $purchase->tax_amount = $taxTotal;
            $purchase->total_amount = $totalAmount;
            if ($paidAmount >= $totalAmount) {
                $purchase->payment_status = 'paid';
            } elseif ($paidAmount > 0) {
                $purchase->payment_status = 'partially_paid';
            } else {
                $purchase->payment_status = 'unpaid';
            }
            $purchase->save();

            return $purchase->load(['supplier', 'items.product', 'movements']);
        });
    }

    /**
     * Edit a posted purchase with controlled stock adjustments.
     */
    public function updatePurchase(Purchase $purchase, array $data, ?User $user = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $data, $user) {
            $purchaseDate = ! empty($data['purchase_date'])
                ? Carbon::parse($data['purchase_date'], 'Asia/Kolkata')->toDateString()
                : $purchase->purchase_date->toDateString();

            // Reverse existing stock movements for this purchase
            $existingMovements = InventoryMovement::where('reference_type', 'purchase')
                ->where('reference_id', $purchase->id)
                ->where('movement_type', 'purchase_in')
                ->get();

            foreach ($existingMovements as $mov) {
                $product = Product::find($mov->product_id);
                if ($product && $product->track_stock) {
                    $this->recordMovement(
                        productOrId: $product,
                        movementType: 'purchase_edit',
                        quantity: $mov->quantity,
                        signedQuantity: -$mov->quantity,
                        referenceType: 'purchase',
                        referenceId: $purchase->id,
                        referenceNo: $purchase->purchase_no,
                        unitCost: (float) $mov->unit_cost,
                        notes: "Adjustment reversal for edit on Purchase {$purchase->purchase_no}",
                        userId: $user?->id,
                        movementDate: $purchaseDate
                    );
                }
            }

            // Remove existing items
            $purchase->items()->delete();

            // Resolve supplier
            $supplierName = $data['supplier_name'] ?? $purchase->supplier_name;
            if (! empty($data['supplier_id'])) {
                $supplier = Supplier::find($data['supplier_id']);
                if ($supplier) {
                    $supplierName = $supplier->name;
                    $purchase->supplier_id = $supplier->id;
                }
            }

            $purchase->supplier_name = $supplierName;
            $purchase->purchase_date = $purchaseDate;
            if (isset($data['invoice_no'])) {
                $purchase->invoice_no = $data['invoice_no'];
            }
            if (isset($data['notes'])) {
                $purchase->notes = $data['notes'];
            }
            $discountAmount = isset($data['discount_amount']) ? (float) $data['discount_amount'] : (float) $purchase->discount_amount;
            $purchase->discount_amount = $discountAmount;
            $purchase->updated_by = $user?->id;

            $subtotal = 0.00;
            $taxTotal = 0.00;

            foreach ($data['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $qty = (float) $itemData['quantity'];
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Quantity for product '{$product->name}' must be greater than zero."],
                    ]);
                }

                $unitCost = (float) $itemData['unit_cost'];
                $taxRate = isset($itemData['tax_rate']) ? (float) $itemData['tax_rate'] : (float) ($product->tax_rate ?? 0.00);
                $itemDiscount = isset($itemData['discount_amount']) ? (float) $itemData['discount_amount'] : 0.00;

                $lineBase = round($qty * $unitCost, 2);
                $lineTax = round($lineBase * ($taxRate / 100), 2);
                $lineTotal = round($lineBase + $lineTax - $itemDiscount, 2);

                $subtotal += $lineBase;
                $taxTotal += $lineTax;

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $qty,
                    'unit' => $itemData['unit'] ?? $product->unit,
                    'unit_cost' => $unitCost,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $lineTax,
                    'discount_amount' => $itemDiscount,
                    'total_cost' => $lineTotal,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                // Record re-applied stock movement
                if ($product->track_stock) {
                    $this->recordMovement(
                        productOrId: $product,
                        movementType: 'purchase_in',
                        quantity: $qty,
                        signedQuantity: $qty,
                        referenceType: 'purchase',
                        referenceId: $purchase->id,
                        referenceNo: $purchase->purchase_no,
                        unitCost: $unitCost,
                        notes: "Re-posted after edit on Purchase {$purchase->purchase_no}",
                        userId: $user?->id,
                        movementDate: $purchaseDate
                    );

                    $product->cost_price = $unitCost;
                    $product->save();
                }
            }

            $totalAmount = round($subtotal + $taxTotal - $discountAmount, 2);
            $purchase->subtotal = $subtotal;
            $purchase->tax_amount = $taxTotal;
            $purchase->total_amount = $totalAmount;
            $purchase->save();

            return $purchase->load(['supplier', 'items.product', 'movements']);
        });
    }

    /**
     * Reverse a posted purchase and deduct inventory for stock-tracked products.
     */
    public function reversePurchase(Purchase $purchase, ?string $reason = null, ?User $user = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $reason, $user) {
            if ($purchase->status === 'cancelled') {
                return $purchase;
            }

            // Reverse stock for all tracked items
            foreach ($purchase->items as $item) {
                $product = $item->product;
                if ($product && $product->track_stock) {
                    $this->recordMovement(
                        productOrId: $product,
                        movementType: 'purchase_reversal',
                        quantity: (float) $item->quantity,
                        signedQuantity: -((float) $item->quantity),
                        referenceType: 'purchase',
                        referenceId: $purchase->id,
                        referenceNo: $purchase->purchase_no,
                        unitCost: (float) $item->unit_cost,
                        notes: 'Reversal: '.($reason ?? 'Purchase cancelled'),
                        userId: $user?->id
                    );
                }
            }

            $purchase->status = 'cancelled';
            $purchase->notes = ($purchase->notes ? $purchase->notes."\n" : '').'Reversed: '.($reason ?? 'Cancelled by user');
            $purchase->updated_by = $user?->id;
            $purchase->save();

            return $purchase->load(['supplier', 'items.product', 'movements']);
        });
    }

    /**
     * Record dealer supply delivery as stock issue only for products configured for stock tracking.
     */
    public function recordSupplyStockIssue(DealerSupply $supply, ?User $user = null): void
    {
        foreach ($supply->items as $item) {
            $product = $item->product ?? Product::find($item->product_id);
            if ($product && $product->track_stock) {
                $this->recordMovement(
                    productOrId: $product,
                    movementType: 'supply_out',
                    quantity: (float) $item->quantity,
                    signedQuantity: -((float) $item->quantity),
                    referenceType: 'dealer_supply',
                    referenceId: $supply->id,
                    referenceNo: $supply->supply_no,
                    unitCost: (float) ($product->cost_price ?? 0.00),
                    notes: "Stock issued for supply {$supply->supply_no} to dealer ".($supply->dealer->name ?? 'Dealer'),
                    userId: $user?->id,
                    movementDate: $supply->supply_date->toDateString()
                );
            }
        }
    }

    /**
     * Reverse stock issues when a supply is updated or deleted.
     */
    public function reverseSupplyStockIssue(DealerSupply $supply, ?User $user = null): void
    {
        $movements = InventoryMovement::where('reference_type', 'dealer_supply')
            ->where('reference_id', $supply->id)
            ->where('movement_type', 'supply_out')
            ->get();

        foreach ($movements as $mov) {
            $product = Product::find($mov->product_id);
            if ($product && $product->track_stock) {
                $this->recordMovement(
                    productOrId: $product,
                    movementType: 'supply_out',
                    quantity: (float) $mov->quantity,
                    signedQuantity: (float) $mov->quantity, // add back
                    referenceType: 'dealer_supply',
                    referenceId: $supply->id,
                    referenceNo: $supply->supply_no,
                    unitCost: (float) $mov->unit_cost,
                    notes: "Reversal of stock issue for supply {$supply->supply_no}",
                    userId: $user?->id,
                    movementDate: $supply->supply_date->toDateString()
                );
            }
        }
    }

    /**
     * Create an authorized stock adjustment with reasons.
     */
    public function createStockAdjustment(array $data, ?User $user = null): StockAdjustment
    {
        return DB::transaction(function () use ($data, $user) {
            $product = Product::findOrFail($data['product_id']);
            $quantity = (float) $data['quantity'];
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Adjustment quantity must be greater than zero.'],
                ]);
            }

            $date = ! empty($data['adjustment_date'])
                ? Carbon::parse($data['adjustment_date'], 'Asia/Kolkata')->toDateString()
                : Carbon::today('Asia/Kolkata')->toDateString();

            $companyId = $product->company_id ?? $user?->company_id ?? Auth::user()?->company_id;
            $dateSlug = Carbon::parse($date)->format('Ymd');
            $adjQuery = StockAdjustment::withoutGlobalScopes()->withTrashed();
            if ($companyId) {
                $adjQuery->where('company_id', $companyId);
            }
            $count = (clone $adjQuery)->whereDate('adjustment_date', $date)->count() + 1;
            do {
                $adjustmentNo = sprintf('ADJ-%s-%04d', $dateSlug, $count);
                $count++;
            } while ((clone $adjQuery)->where('adjustment_no', $adjustmentNo)->exists());

            $type = $data['adjustment_type']; // addition, reduction, damage, leakage, audit_reconciliation, expiry, loss
            $isAddition = in_array($type, ['addition']);
            $signedQuantity = $isAddition ? $quantity : -$quantity;

            $costPrice = isset($data['cost_price']) ? (float) $data['cost_price'] : (float) ($product->cost_price ?? 0.00);

            $stockBefore = (float) $product->current_stock;
            $stockAfter = round($stockBefore + $signedQuantity, 2);

            $adjustment = StockAdjustment::create([
                'company_id' => $companyId,
                'adjustment_no' => $adjustmentNo,
                'adjustment_date' => $date,
                'product_id' => $product->id,
                'adjustment_type' => $type,
                'quantity' => $quantity,
                'unit' => $data['unit'] ?? $product->unit,
                'cost_price' => $costPrice,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reason' => $data['reason'] ?? 'Inventory Adjustment',
                'authorized_by' => $data['authorized_by'] ?? ($user?->name ?? 'Administrator'),
                'authorized_by_id' => $user?->id,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            // If product is stock tracked, record movement and update current stock
            if ($product->track_stock) {
                $this->recordMovement(
                    productOrId: $product,
                    movementType: 'stock_adjustment',
                    quantity: $quantity,
                    signedQuantity: $signedQuantity,
                    referenceType: 'stock_adjustment',
                    referenceId: $adjustment->id,
                    referenceNo: $adjustment->adjustment_no,
                    unitCost: $costPrice,
                    notes: "Stock adjustment: {$type} - {$adjustment->reason} (Auth: {$adjustment->authorized_by})",
                    userId: $user?->id,
                    movementDate: $date
                );
            }

            return $adjustment->load(['product', 'authorizedUser', 'creator']);
        });
    }

    /**
     * Inventory summary report for KPI cards and dashboard integration.
     */
    public function getInventorySummary(): array
    {
        $trackedProducts = Product::where('track_stock', true)->where('is_active', true)->get();

        $totalStock = (float) $trackedProducts->sum('current_stock');
        $lowStockCount = $trackedProducts->filter(fn ($p) => (float) $p->current_stock <= (float) $p->min_stock_level)->count();

        $lowStockItems = $trackedProducts
            ->filter(fn ($p) => (float) $p->current_stock <= (float) $p->min_stock_level)
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'unit' => $p->unit,
                'current_stock' => (float) $p->current_stock,
                'min_stock_level' => (float) $p->min_stock_level,
                'cost_price' => (float) $p->cost_price,
            ])
            ->values();

        // Total received and total issued
        $totalReceived = (float) InventoryMovement::whereIn('movement_type', ['purchase_in', 'addition'])
            ->sum('quantity');

        $totalIssued = (float) InventoryMovement::whereIn('movement_type', ['supply_out', 'reduction', 'damage', 'damage_out', 'leakage', 'expiry', 'loss'])
            ->sum('quantity');

        return [
            'total_tracked_products' => $trackedProducts->count(),
            'current_total_stock' => round($totalStock, 2),
            'total_received' => round($totalReceived, 2),
            'total_issued' => round($totalIssued, 2),
            'low_stock_count' => $lowStockCount,
            'low_stock_items' => $lowStockItems,
        ];
    }
}
