<?php

namespace App\Services;

use App\Models\DamagedProduct;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DamagedProductService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Generate unique Damage Entry Number (e.g. DMG-20261106-0001)
     */
    public function generateDamageNo(?int $companyId = null, ?string $date = null): string
    {
        $targetDate = ! empty($date)
            ? Carbon::parse($date, 'Asia/Kolkata')
            : Carbon::today('Asia/Kolkata');

        $dateSlug = $targetDate->format('Ymd');
        $query = DamagedProduct::withoutGlobalScopes();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $count = (clone $query)->whereDate('damage_date', $targetDate->toDateString())->withTrashed()->count() + 1;

        do {
            $damageNo = sprintf('DMG-%s-%04d', $dateSlug, $count);
            $count++;
        } while ((clone $query)->where('damage_no', $damageNo)->withTrashed()->exists());

        return $damageNo;
    }

    /**
     * Record a new damaged product entry and automatically reduce stock.
     */
    public function create(array $data, ?User $user = null): DamagedProduct
    {
        $companyId = $user?->company_id ?? Auth::user()?->company_id;

        return DB::transaction(function () use ($data, $user, $companyId) {
            $productId = $data['product_id'];
            $damageQty = round((float) $data['quantity'], 2);

            if ($damageQty <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Damaged quantity must be greater than zero.'],
                ]);
            }

            // Lock product row to prevent race conditions
            $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

            $availableStock = (float) $product->current_stock;
            if ($damageQty > $availableStock) {
                throw ValidationException::withMessages([
                    'quantity' => ["Damaged quantity ({$damageQty}) cannot exceed current available stock ({$availableStock} {$product->unit})."],
                ]);
            }

            $damageDate = ! empty($data['damage_date'])
                ? Carbon::parse($data['damage_date'], 'Asia/Kolkata')->toDateString()
                : Carbon::today('Asia/Kolkata')->toDateString();

            $damageNo = ! empty($data['damage_no'])
                ? trim($data['damage_no'])
                : $this->generateDamageNo($companyId, $damageDate);

            // Check uniqueness of damage_no
            $exists = DamagedProduct::withoutGlobalScopes()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->where('damage_no', $damageNo)
                ->exists();

            if ($exists) {
                $damageNo = $this->generateDamageNo($companyId, $damageDate);
            }

            $unitCost = (float) ($product->cost_price ?? 0.00);
            $totalCost = round($damageQty * $unitCost, 2);
            $stockBefore = $availableStock;
            $stockAfter = round($availableStock - $damageQty, 2);

            $entry = DamagedProduct::create([
                'company_id' => $companyId,
                'damage_no' => $damageNo,
                'damage_date' => $damageDate,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'variant' => $data['variant'] ?? ($product->unit ?? null),
                'quantity' => $damageQty,
                'unit' => $product->unit,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reason' => $data['reason'],
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $user?->id ?? Auth::id(),
            ]);

            // Atomic inventory movement with negative quantity (DAMAGE OUT)
            $this->inventoryService->recordMovement(
                productOrId: $product,
                movementType: 'damage_out',
                quantity: $damageQty,
                signedQuantity: -$damageQty,
                referenceType: 'damaged_product',
                referenceId: $entry->id,
                referenceNo: $entry->damage_no,
                unitCost: $unitCost,
                notes: "Damaged Stock ({$entry->reason})".($entry->remarks ? " - {$entry->remarks}" : ''),
                userId: $user?->id ?? Auth::id(),
                movementDate: $damageDate
            );

            return $entry->fresh(['product', 'creator']);
        });
    }

    /**
     * Update an existing damaged product entry and accurately re-adjust stock.
     */
    public function update(DamagedProduct $entry, array $data, ?User $user = null): DamagedProduct
    {
        return DB::transaction(function () use ($entry, $data, $user) {
            $newQty = round((float) $data['quantity'], 2);
            if ($newQty <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Damaged quantity must be greater than zero.'],
                ]);
            }

            $productId = $entry->product_id;
            $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

            $oldQty = (float) $entry->quantity;
            // Virtual stock restored before applying new deduction
            $effectiveStock = round((float) $product->current_stock + $oldQty, 2);

            if ($newQty > $effectiveStock) {
                throw ValidationException::withMessages([
                    'quantity' => ["Updated quantity ({$newQty}) exceeds available stock ({$effectiveStock} {$product->unit})."],
                ]);
            }

            $diff = round($newQty - $oldQty, 2);
            $stockBefore = (float) $product->current_stock;
            $stockAfter = round($stockBefore - $diff, 2);

            // Update product current stock
            $product->current_stock = $stockAfter;
            $product->save();

            $unitCost = (float) ($product->cost_price ?? 0.00);
            $totalCost = round($newQty * $unitCost, 2);

            $damageDate = ! empty($data['damage_date'])
                ? Carbon::parse($data['damage_date'], 'Asia/Kolkata')->toDateString()
                : $entry->damage_date->toDateString();

            $entry->update([
                'damage_date' => $damageDate,
                'variant' => $data['variant'] ?? $entry->variant,
                'quantity' => $newQty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'stock_after' => $stockAfter,
                'reason' => $data['reason'] ?? $entry->reason,
                'remarks' => $data['remarks'] ?? $entry->remarks,
                'updated_by' => $user?->id ?? Auth::id(),
            ]);

            // Update or record the inventory movement
            $movement = InventoryMovement::where('reference_type', 'damaged_product')
                ->where('reference_id', $entry->id)
                ->first();

            if ($movement) {
                $movement->update([
                    'quantity' => $newQty,
                    'signed_quantity' => -$newQty,
                    'total_cost' => $totalCost,
                    'balance_after' => $stockAfter,
                    'notes' => "Damaged Stock ({$entry->reason})".($entry->remarks ? " - {$entry->remarks}" : ''),
                    'movement_date' => $damageDate,
                ]);
            } else {
                $this->inventoryService->recordMovement(
                    productOrId: $product,
                    movementType: 'damage_out',
                    quantity: $newQty,
                    signedQuantity: -$diff,
                    referenceType: 'damaged_product',
                    referenceId: $entry->id,
                    referenceNo: $entry->damage_no,
                    unitCost: $unitCost,
                    notes: "Damaged Stock Edit ({$entry->reason})",
                    userId: $user?->id ?? Auth::id(),
                    movementDate: $damageDate
                );
            }

            return $entry->fresh(['product', 'creator', 'updater']);
        });
    }

    /**
     * Delete a damaged product entry, restoring deducted stock back to the product.
     */
    public function delete(DamagedProduct $entry, ?User $user = null): bool
    {
        return DB::transaction(function () use ($entry, $user) {
            $product = Product::where('id', $entry->product_id)->lockForUpdate()->firstOrFail();
            $restoreQty = (float) $entry->quantity;

            // Record movement reversal (recordMovement handles restoring product->current_stock atomically)
            $this->inventoryService->recordMovement(
                productOrId: $product,
                movementType: 'damage_reversal',
                quantity: $restoreQty,
                signedQuantity: $restoreQty,
                referenceType: 'damaged_product_reversal',
                referenceId: $entry->id,
                referenceNo: $entry->damage_no,
                unitCost: (float) $entry->unit_cost,
                notes: "Reversal of Damaged Stock {$entry->damage_no}",
                userId: $user?->id ?? Auth::id(),
                movementDate: Carbon::today('Asia/Kolkata')->toDateString()
            );

            // Soft delete damaged product entry
            return (bool) $entry->delete();
        });
    }

    /**
     * Summary metrics for dashboard and reports.
     */
    public function getSummary(?int $companyId = null): array
    {
        $today = Carbon::today('Asia/Kolkata')->toDateString();
        $startOfMonth = Carbon::now('Asia/Kolkata')->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now('Asia/Kolkata')->endOfMonth()->toDateString();

        $baseQuery = DamagedProduct::query();
        if ($companyId) {
            $baseQuery->where('company_id', $companyId);
        }

        $todayQty = (float) (clone $baseQuery)->whereDate('damage_date', $today)->sum('quantity');
        $thisMonthQty = (float) (clone $baseQuery)->whereBetween('damage_date', [$startOfMonth, $endOfMonth])->sum('quantity');
        $totalQty = (float) (clone $baseQuery)->sum('quantity');
        $totalCost = (float) (clone $baseQuery)->sum('total_cost');

        $productBreakdown = (clone $baseQuery)
            ->select('product_id', 'product_name', 'variant', 'unit', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(total_cost) as total_amount'), DB::raw('COUNT(*) as entries_count'))
            ->groupBy('product_id', 'product_name', 'variant', 'unit')
            ->orderByDesc('total_quantity')
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'variant' => $item->variant,
                    'unit' => $item->unit,
                    'total_quantity' => (float) $item->total_quantity,
                    'total_amount' => (float) $item->total_amount,
                    'entries_count' => (int) $item->entries_count,
                ];
            });

        return [
            'today_damaged_qty' => $todayQty,
            'this_month_damaged_qty' => $thisMonthQty,
            'total_damaged_qty' => $totalQty,
            'total_damaged_cost' => $totalCost,
            'product_breakdown' => $productBreakdown,
        ];
    }
}
