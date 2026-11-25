<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\PublicParty;
use App\Models\PublicSale;
use App\Models\PublicSaleItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PublicSalesService
{
    /**
     * Get all active products for the company configured as 'Without for Invoice',
     * including applicable refill products.
     */
    public function getEligibleProducts(int $companyId): Collection
    {
        return Product::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('for_invoice')
                    ->orWhere('for_invoice', false);
            })
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * List all public parties belonging to the company.
     */
    public function listParties(int $companyId, ?string $search = null, ?string $status = null)
    {
        $query = PublicParty::withoutGlobalScopes()
            ->where('company_id', $companyId);

        if (! empty($status)) {
            $query->where('status', $status);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('party_name', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        return $query->withCount('sales')
            ->withSum('sales as total_sales_amount', 'total_amount')
            ->orderBy('party_name', 'asc')
            ->get();
    }

    /**
     * Create a new party for the company.
     */
    public function createParty(int $companyId, array $data): PublicParty
    {
        return PublicParty::create([
            'company_id' => $companyId,
            'party_name' => trim($data['party_name']),
            'contact_number' => ! empty($data['contact_number']) ? trim($data['contact_number']) : null,
            'address' => ! empty($data['address']) ? trim($data['address']) : null,
            'status' => $data['status'] ?? 'active',
        ]);
    }

    /**
     * Update an existing party.
     */
    public function updateParty(PublicParty $party, array $data): PublicParty
    {
        $party->update([
            'party_name' => trim($data['party_name'] ?? $party->party_name),
            'contact_number' => array_key_exists('contact_number', $data) ? (! empty($data['contact_number']) ? trim($data['contact_number']) : null) : $party->contact_number,
            'address' => array_key_exists('address', $data) ? (! empty($data['address']) ? trim($data['address']) : null) : $party->address,
            'status' => $data['status'] ?? $party->status,
        ]);

        return $party;
    }

    /**
     * Delete a party with transaction history protection.
     */
    public function deleteParty(PublicParty $party): bool
    {
        if ($party->hasTransactions()) {
            throw ValidationException::withMessages([
                'party' => ['Cannot delete party with existing transaction history. Sales and statements must be preserved.'],
            ]);
        }

        return $party->delete();
    }

    /**
     * Create a public sales entry with atomic inventory movement integration.
     */
    public function createSale(int $companyId, array $data, ?User $user = null): PublicSale
    {
        return DB::transaction(function () use ($companyId, $data, $user) {
            $party = PublicParty::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->findOrFail($data['party_id']);

            $saleDate = Carbon::parse($data['sale_date'], 'Asia/Kolkata')->toDateString();
            $exactTime = Carbon::now('Asia/Kolkata')->format('h:i:s A');
            $confirmInsufficient = (bool) ($data['confirm_insufficient_stock'] ?? false);

            if (empty($data['items']) || ! is_array($data['items'])) {
                throw ValidationException::withMessages([
                    'items' => ['At least one product item is required.'],
                ]);
            }

            // 1. Pre-validate stock availability for all stock-tracked items
            $insufficientItems = [];
            foreach ($data['items'] as $itemData) {
                $product = Product::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->findOrFail($itemData['product_id']);

                $qty = (float) ($itemData['quantity'] ?? 0);
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'quantity' => ["Quantity for product '{$product->name}' must be greater than zero."],
                    ]);
                }

                $trackingStarted = (bool) $product->track_stock &&
                    ($product->inventory_start_date === null || $saleDate >= $product->inventory_start_date->toDateString());

                if ($trackingStarted) {
                    $available = (float) $product->current_stock;
                    if ($available < $qty && ! $confirmInsufficient) {
                        $insufficientItems[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'available_stock' => $available,
                            'requested_quantity' => $qty,
                        ];
                    }
                }
            }

            if (! empty($insufficientItems)) {
                throw ValidationException::withMessages([
                    'stock_error' => ['Stock Not Available. Stock illa. Public Sales-la add pannalama?'],
                    'stock_insufficient' => [true],
                    'insufficient_items' => $insufficientItems,
                ]);
            }

            $cashAmount = (float) ($data['cash_amount'] ?? 0.00);
            $upiAmount = (float) ($data['upi_amount'] ?? 0.00);
            $totalAmount = 0.00;

            // 2. Create the PublicSale header
            $sale = PublicSale::create([
                'company_id' => $companyId,
                'party_id' => $party->id,
                'sale_date' => $saleDate,
                'sale_time' => $exactTime,
                'cash_amount' => $cashAmount,
                'upi_amount' => $upiAmount,
                'total_amount' => 0.00,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            // 3. Process items and deduct stock
            $dateSlug = Carbon::parse($saleDate)->format('Ymd');
            foreach ($data['items'] as $itemData) {
                $product = Product::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->findOrFail($itemData['product_id']);

                $qty = (float) $itemData['quantity'];
                $rate = (float) ($itemData['rate'] ?? ($product->default_price ?? 0.00));
                // Allow manual amount modification; fallback to rate * qty
                $amount = isset($itemData['amount']) ? (float) $itemData['amount'] : round($rate * $qty, 2);
                $totalAmount += $amount;

                $trackingStarted = (bool) $product->track_stock &&
                    ($product->inventory_start_date === null || $saleDate >= $product->inventory_start_date->toDateString());

                $deductedStock = 0.00;
                if ($trackingStarted) {
                    $available = (float) $product->current_stock;
                    if ($available >= $qty) {
                        // Case B: Sufficient stock -> deduct requested quantity
                        $deductedStock = $qty;
                    } else {
                        // Case C: Insufficient or zero stock confirmed -> do NOT deduct any stock, keep current stock unchanged
                        $deductedStock = 0.00;
                    }

                    if ($deductedStock > 0) {
                        $balanceBefore = (float) $product->current_stock;
                        $balanceAfter = round($balanceBefore - $deductedStock, 2);
                        $product->current_stock = $balanceAfter;
                        $product->save();

                        // Generate unique movement number
                        $movQuery = InventoryMovement::withoutGlobalScopes();
                        $movCount = (clone $movQuery)->whereDate('movement_date', $saleDate)->count() + 1;
                        do {
                            $movementNo = sprintf('MOV-%s-%04d', $dateSlug, $movCount);
                            $movCount++;
                        } while ((clone $movQuery)->where('movement_no', $movementNo)->exists());

                        InventoryMovement::create([
                            'company_id' => $companyId,
                            'movement_no' => $movementNo,
                            'movement_date' => $saleDate,
                            'product_id' => $product->id,
                            'movement_type' => 'public_sale_out',
                            'reference_type' => 'public_sale',
                            'reference_id' => $sale->id,
                            'reference_no' => "PS-{$sale->id}",
                            'quantity' => $deductedStock,
                            'signed_quantity' => -$deductedStock,
                            'unit' => $product->unit,
                            'unit_cost' => (float) ($product->cost_price ?? 0.00),
                            'total_cost' => round($deductedStock * (float) ($product->cost_price ?? 0.00), 2),
                            'balance_before' => $balanceBefore,
                            'balance_after' => $balanceAfter,
                            'notes' => "Public Sale to {$party->party_name} [PS-{$sale->id}]",
                            'created_by' => $user?->id,
                        ]);
                    }
                }

                PublicSaleItem::create([
                    'public_sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'unit_snapshot' => $product->unit,
                    'rate' => $rate,
                    'quantity' => $qty,
                    'amount' => $amount,
                    'stock_deducted' => $deductedStock,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $sale->total_amount = round($totalAmount, 2);
            $sale->save();

            return $sale->load(['party', 'items.product', 'creator']);
        });
    }

    /**
     * Update an existing public sales entry and re-adjust stock movements accurately.
     */
    public function updateSale(PublicSale $sale, array $data, ?User $user = null): PublicSale
    {
        return DB::transaction(function () use ($sale, $data, $user) {
            $companyId = $sale->company_id;
            $saleDate = ! empty($data['sale_date'])
                ? Carbon::parse($data['sale_date'], 'Asia/Kolkata')->toDateString()
                : $sale->sale_date->toDateString();

            $partyId = ! empty($data['party_id']) ? (int) $data['party_id'] : $sale->party_id;
            $party = PublicParty::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->findOrFail($partyId);

            $confirmInsufficient = (bool) ($data['confirm_insufficient_stock'] ?? false);

            // 1. Reverse previously deducted stock for all current items
            foreach ($sale->items as $oldItem) {
                if ($oldItem->stock_deducted > 0) {
                    $product = Product::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->lockForUpdate()
                        ->find($oldItem->product_id);

                    if ($product && $product->track_stock) {
                        $product->current_stock = round((float) $product->current_stock + (float) $oldItem->stock_deducted, 2);
                        $product->save();
                    }
                }
            }

            // Remove previous inventory movement records linked to this sale
            InventoryMovement::where('reference_type', 'public_sale')
                ->where('reference_id', $sale->id)
                ->delete();

            // 2. Validate stock for new items after reversal
            if (empty($data['items']) || ! is_array($data['items'])) {
                throw ValidationException::withMessages([
                    'items' => ['At least one product item is required.'],
                ]);
            }

            $insufficientItems = [];
            foreach ($data['items'] as $itemData) {
                $product = Product::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->findOrFail($itemData['product_id']);

                $qty = (float) ($itemData['quantity'] ?? 0);
                $trackingStarted = (bool) $product->track_stock &&
                    ($product->inventory_start_date === null || $saleDate >= $product->inventory_start_date->toDateString());

                if ($trackingStarted) {
                    $available = (float) $product->current_stock;
                    if ($available < $qty && ! $confirmInsufficient) {
                        $insufficientItems[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'available_stock' => $available,
                            'requested_quantity' => $qty,
                        ];
                    }
                }
            }

            if (! empty($insufficientItems)) {
                throw ValidationException::withMessages([
                    'stock_error' => ['Stock Not Available. Stock illa. Public Sales-la add pannalama?'],
                    'stock_insufficient' => [true],
                    'insufficient_items' => $insufficientItems,
                ]);
            }

            // Delete old items
            $sale->items()->delete();

            $cashAmount = isset($data['cash_amount']) ? (float) $data['cash_amount'] : (float) $sale->cash_amount;
            $upiAmount = isset($data['upi_amount']) ? (float) $data['upi_amount'] : (float) $sale->upi_amount;
            $totalAmount = 0.00;

            // 3. Re-process items and deduct stock
            $dateSlug = Carbon::parse($saleDate)->format('Ymd');
            foreach ($data['items'] as $itemData) {
                $product = Product::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->findOrFail($itemData['product_id']);

                $qty = (float) $itemData['quantity'];
                $rate = (float) ($itemData['rate'] ?? ($product->default_price ?? 0.00));
                $amount = isset($itemData['amount']) ? (float) $itemData['amount'] : round($rate * $qty, 2);
                $totalAmount += $amount;

                $trackingStarted = (bool) $product->track_stock &&
                    ($product->inventory_start_date === null || $saleDate >= $product->inventory_start_date->toDateString());

                $deductedStock = 0.00;
                if ($trackingStarted) {
                    $available = (float) $product->current_stock;
                    if ($available >= $qty) {
                        // Sufficient stock -> deduct requested quantity
                        $deductedStock = $qty;
                    } else {
                        // Insufficient or zero stock confirmed -> do NOT deduct any stock, keep current stock unchanged
                        $deductedStock = 0.00;
                    }

                    if ($deductedStock > 0) {
                        $balanceBefore = (float) $product->current_stock;
                        $balanceAfter = round($balanceBefore - $deductedStock, 2);
                        $product->current_stock = $balanceAfter;
                        $product->save();

                        $movQuery = InventoryMovement::withoutGlobalScopes();
                        $movCount = (clone $movQuery)->whereDate('movement_date', $saleDate)->count() + 1;
                        do {
                            $movementNo = sprintf('MOV-%s-%04d', $dateSlug, $movCount);
                            $movCount++;
                        } while ((clone $movQuery)->where('movement_no', $movementNo)->exists());

                        InventoryMovement::create([
                            'company_id' => $companyId,
                            'movement_no' => $movementNo,
                            'movement_date' => $saleDate,
                            'product_id' => $product->id,
                            'movement_type' => 'public_sale_out',
                            'reference_type' => 'public_sale',
                            'reference_id' => $sale->id,
                            'reference_no' => "PS-{$sale->id}",
                            'quantity' => $deductedStock,
                            'signed_quantity' => -$deductedStock,
                            'unit' => $product->unit,
                            'unit_cost' => (float) ($product->cost_price ?? 0.00),
                            'total_cost' => round($deductedStock * (float) ($product->cost_price ?? 0.00), 2),
                            'balance_before' => $balanceBefore,
                            'balance_after' => $balanceAfter,
                            'notes' => "Updated Public Sale to {$party->party_name} [PS-{$sale->id}]",
                            'created_by' => $user?->id,
                        ]);
                    }
                }

                PublicSaleItem::create([
                    'public_sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'unit_snapshot' => $product->unit,
                    'rate' => $rate,
                    'quantity' => $qty,
                    'amount' => $amount,
                    'stock_deducted' => $deductedStock,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $sale->update([
                'party_id' => $party->id,
                'sale_date' => $saleDate,
                'cash_amount' => $cashAmount,
                'upi_amount' => $upiAmount,
                'total_amount' => round($totalAmount, 2),
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $sale->notes,
            ]);

            return $sale->load(['party', 'items.product', 'creator']);
        });
    }

    /**
     * Delete a public sales entry and safely reverse its inventory movements.
     */
    public function deleteSale(PublicSale $sale, ?User $user = null): bool
    {
        return DB::transaction(function () use ($sale) {
            $companyId = $sale->company_id;

            // Reverse deducted stock for each item
            foreach ($sale->items as $item) {
                if ($item->stock_deducted > 0) {
                    $product = Product::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->lockForUpdate()
                        ->find($item->product_id);

                    if ($product && $product->track_stock) {
                        $product->current_stock = round((float) $product->current_stock + (float) $item->stock_deducted, 2);
                        $product->save();
                    }
                }
            }

            // Remove inventory movement entries
            InventoryMovement::where('reference_type', 'public_sale')
                ->where('reference_id', $sale->id)
                ->delete();

            // Delete items and sale record
            $sale->items()->delete();
            return $sale->delete();
        });
    }

    /**
     * Get Party Monthly Statement.
     */
    public function getPartyMonthlyStatement(int $companyId, int $partyId, int $year, int $month): array
    {
        $party = PublicParty::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($partyId);

        $startDate = Carbon::createFromDate($year, $month, 1, 'Asia/Kolkata')->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();
        $daysInMonth = $startDate->daysInMonth;
        $monthName = $startDate->format('F Y');

        // Fetch all sales for this party in the specified month
        $sales = PublicSale::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('party_id', $party->id)
            ->whereBetween('sale_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with(['items.product'])
            ->orderBy('sale_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        // 1. Collect all distinct products dynamically from catalog & transaction history
        $catalogProducts = $this->getEligibleProducts($companyId);
        $productKeys = [];
        $productIdToName = [];

        foreach ($catalogProducts as $cp) {
            $productKeys[$cp->id] = $cp->name;
            $productIdToName[$cp->id] = $cp->name;
        }

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                if (! isset($productKeys[$item->product_id])) {
                    $productKeys[$item->product_id] = $item->product_name_snapshot;
                    $productIdToName[$item->product_id] = $item->product_name_snapshot;
                }
            }
        }

        // 2. Build date-wise records
        $salesByDate = $sales->groupBy(function ($s) {
            return $s->sale_date->format('Y-m-d');
        });

        $dailyRecords = [];
        $monthlyProductTotals = array_fill_keys(array_keys($productKeys), 0.0);
        $monthlyTotalCash = 0.00;
        $monthlyTotalUpi = 0.00;
        $monthlyGrandTotal = 0.00;
        $monthlyTotalEntries = 0;

        foreach ($salesByDate as $dateStr => $daySales) {
            $dayCarbon = Carbon::parse($dateStr);
            $dayProductQtys = array_fill_keys(array_keys($productKeys), 0.0);
            $dayCash = 0.00;
            $dayUpi = 0.00;
            $dayTotal = 0.00;
            $dayEntriesCount = $daySales->count();

            foreach ($daySales as $sale) {
                $dayCash += (float) $sale->cash_amount;
                $dayUpi += (float) $sale->upi_amount;
                $dayTotal += (float) $sale->total_amount;

                foreach ($sale->items as $item) {
                    $pId = $item->product_id;
                    if (isset($dayProductQtys[$pId])) {
                        $dayProductQtys[$pId] += (float) $item->quantity;
                    }
                    if (isset($monthlyProductTotals[$pId])) {
                        $monthlyProductTotals[$pId] += (float) $item->quantity;
                    }
                }
            }

            $monthlyTotalCash += $dayCash;
            $monthlyTotalUpi += $dayUpi;
            $monthlyGrandTotal += $dayTotal;
            $monthlyTotalEntries += $dayEntriesCount;

            // Format product quantities with product names
            $namedProductQtys = [];
            foreach ($dayProductQtys as $pId => $qty) {
                $namedProductQtys[$productKeys[$pId]] = round($qty, 2);
            }

            $dailyRecords[] = [
                'date' => $dateStr,
                'day' => (int) $dayCarbon->format('d'),
                'day_name' => $dayCarbon->format('D'),
                'formatted_date' => $dayCarbon->format('d M Y'),
                'entries_count' => $dayEntriesCount,
                'product_quantities' => $namedProductQtys,
                'cash_amount' => round($dayCash, 2),
                'upi_amount' => round($dayUpi, 2),
                'daily_total' => round($dayTotal, 2),
            ];
        }

        // Format monthly product totals with product names
        $namedMonthlyProductTotals = [];
        foreach ($monthlyProductTotals as $pId => $qty) {
            $namedMonthlyProductTotals[$productKeys[$pId]] = round($qty, 2);
        }

        $company = Company::find($companyId);
        $settings = CompanySetting::where('company_id', $companyId)->first();
        $logoUrl = $settings?->logo_url ?: $company?->logo_url;

        return [
            'company' => [
                'id' => $companyId,
                'name' => $settings->company_name ?? $company?->name ?? 'DockRent',
                'tagline' => $settings->tagline ?? 'Unified Operations Cloud',
                'address' => $settings->address ?? '',
                'phone' => $settings->effectiveContactNumber ?? $settings->phone ?? '',
                'gstin' => $settings->gstin ?? '',
                'logo_url' => $logoUrl,
            ],
            'party' => [
                'id' => $party->id,
                'party_name' => $party->party_name,
                'contact_number' => $party->contact_number,
                'address' => $party->address,
                'status' => $party->status,
            ],
            'period' => [
                'year' => $year,
                'month' => $month,
                'month_name' => $monthName,
                'days_in_month' => $daysInMonth,
            ],
            'product_columns' => array_values($productKeys),
            'daily_records' => $dailyRecords,
            'monthly_totals' => [
                'product_quantities' => $namedMonthlyProductTotals,
                'total_cash' => round($monthlyTotalCash, 2),
                'total_upi' => round($monthlyTotalUpi, 2),
                'grand_total' => round($monthlyGrandTotal, 2),
                'total_entries' => $monthlyTotalEntries,
            ],
        ];
    }

    /**
     * Get Party Daily Sales Details (all entries on a single date, sorted by created_at).
     */
    public function getPartyDailyDetails(int $companyId, int $partyId, string $date): array
    {
        $party = PublicParty::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($partyId);

        $parsedDate = Carbon::parse($date, 'Asia/Kolkata')->toDateString();

        $sales = PublicSale::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('party_id', $party->id)
            ->whereDate('sale_date', $parsedDate)
            ->with(['items.product', 'creator'])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $entries = [];
        $dayProductTotals = [];
        $dayTotalCash = 0.00;
        $dayTotalUpi = 0.00;
        $dayGrandTotal = 0.00;

        foreach ($sales as $sale) {
            $dayTotalCash += (float) $sale->cash_amount;
            $dayTotalUpi += (float) $sale->upi_amount;
            $dayGrandTotal += (float) $sale->total_amount;

            $itemsData = [];
            foreach ($sale->items as $item) {
                $pName = $item->product_name_snapshot;
                if (! isset($dayProductTotals[$pName])) {
                    $dayProductTotals[$pName] = 0.0;
                }
                $dayProductTotals[$pName] += (float) $item->quantity;

                $itemsData[] = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name_snapshot,
                    'unit' => $item->unit_snapshot ?? 'Unit',
                    'rate' => (float) $item->rate,
                    'quantity' => (float) $item->quantity,
                    'amount' => (float) $item->amount,
                    'stock_deducted' => (float) $item->stock_deducted,
                    'notes' => $item->notes,
                ];
            }

            // Exact timestamp preservation
            $exactTime = $sale->sale_time ?: $sale->created_at->timezone('Asia/Kolkata')->format('h:i:s A');

            $entries[] = [
                'id' => $sale->id,
                'entry_no' => "PS-{$sale->id}",
                'created_at' => $sale->created_at->toIso8601String(),
                'exact_time' => $exactTime,
                'cash_amount' => (float) $sale->cash_amount,
                'upi_amount' => (float) $sale->upi_amount,
                'total_amount' => (float) $sale->total_amount,
                'notes' => $sale->notes,
                'created_by_name' => $sale->creator?->name ?? 'System',
                'items' => $itemsData,
            ];
        }

        $company = Company::find($companyId);
        $settings = CompanySetting::where('company_id', $companyId)->first();
        $logoUrl = $settings?->logo_url ?: $company?->logo_url;

        return [
            'company' => [
                'id' => $companyId,
                'name' => $settings->company_name ?? $company?->name ?? 'DockRent',
                'tagline' => $settings->tagline ?? 'Unified Operations Cloud',
                'address' => $settings->address ?? '',
                'phone' => $settings->effectiveContactNumber ?? $settings->phone ?? '',
                'gstin' => $settings->gstin ?? '',
                'logo_url' => $logoUrl,
            ],
            'party' => [
                'id' => $party->id,
                'party_name' => $party->party_name,
                'contact_number' => $party->contact_number,
                'address' => $party->address,
                'status' => $party->status,
            ],
            'date' => $parsedDate,
            'formatted_date' => Carbon::parse($parsedDate)->format('d F Y'),
            'entries' => $entries,
            'day_summary' => [
                'total_entries' => count($entries),
                'product_totals' => array_map(fn ($q) => round($q, 2), $dayProductTotals),
                'total_cash' => round($dayTotalCash, 2),
                'total_upi' => round($dayTotalUpi, 2),
                'daily_grand_total' => round($dayGrandTotal, 2),
            ],
        ];
    }

    /**
     * Resolve the company logo to its server-side physical file path for DomPDF generation.
     * Prevents external HTTP requests and eliminates Base64 memory overhead.
     */
    public function getCompanyLogoFilePath(int $companyId): ?string
    {
        $company = Company::find($companyId);
        $settings = CompanySetting::where('company_id', $companyId)->first();

        $logoPath = $company?->logo_path ?: $settings?->logo_path;

        if ($logoPath) {
            $disk = Storage::disk('public');
            $cleanPath = ltrim(str_replace('public/', '', $logoPath), '/');

            // 1. Storage disk('public') path
            if ($disk->exists($cleanPath)) {
                $fullPath = $disk->path($cleanPath);
                if (file_exists($fullPath)) {
                    return $fullPath;
                }
            }

            // 2. Check storage_path('app/public/...')
            $storagePath = storage_path('app/public/'.$cleanPath);
            if (file_exists($storagePath)) {
                return $storagePath;
            }

            // 3. Check public_path('storage/...')
            $publicStoragePath = public_path('storage/'.$cleanPath);
            if (file_exists($publicStoragePath)) {
                return $publicStoragePath;
            }

            // 4. Check logos/filename directly if cleanPath is just a filename
            $filename = basename($cleanPath);
            if ($disk->exists("logos/{$filename}")) {
                $fullPath = $disk->path("logos/{$filename}");
                if (file_exists($fullPath)) {
                    return $fullPath;
                }
            }

            $storageLogoPath = storage_path('app/public/logos/'.$filename);
            if (file_exists($storageLogoPath)) {
                return $storageLogoPath;
            }

            $publicLogoPath = public_path('storage/logos/'.$filename);
            if (file_exists($publicLogoPath)) {
                return $publicLogoPath;
            }
        }

        return null;
    }

    /**
     * Get local file path (or fallback data URI) for company logo to ensure reliable DomPDF rendering.
     */
    public function getCompanyLogoForPdf(int $companyId): ?string
    {
        $filePath = $this->getCompanyLogoFilePath($companyId);
        if ($filePath && file_exists($filePath)) {
            return $filePath;
        }

        $company = Company::find($companyId);
        $settings = CompanySetting::where('company_id', $companyId)->first();
        $logoUrl = $settings?->logo_url ?: $company?->logo_url;
        if (! empty($logoUrl)) {
            return $logoUrl;
        }

        return null;
    }
}
