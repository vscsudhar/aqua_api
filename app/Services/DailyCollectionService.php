<?php

namespace App\Services;

use App\Models\Dealer;
use App\Models\DealerPayment;
use App\Models\DealerSupply;
use App\Models\DealerSupplyItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyCollectionService
{
    public function filterAttributesForTable(string $table, array $attributes): array
    {
        static $cachedCols = [];
        if (! isset($cachedCols[$table])) {
            try {
                $cachedCols[$table] = \Illuminate\Support\Facades\Schema::getColumnListing($table);
            } catch (\Throwable $e) {
                $cachedCols[$table] = [];
            }
        }
        $validCols = $cachedCols[$table];
        if (empty($validCols)) {
            return $attributes;
        }

        return array_filter($attributes, fn ($k) => in_array($k, $validCols, true), ARRAY_FILTER_USE_KEY);
    }

    public function hasTableColumn(string $table, string $column): bool
    {
        static $cachedCols = [];
        if (! isset($cachedCols[$table])) {
            try {
                $cachedCols[$table] = \Illuminate\Support\Facades\Schema::getColumnListing($table);
            } catch (\Throwable $e) {
                $cachedCols[$table] = [];
            }
        }

        return in_array($column, $cachedCols[$table] ?? [], true);
    }

    /**
     * Creates a new supply transaction with applied price snapshots and optional immediate collection.
     */
    public function createSupply(array $data, ?User $user = null): DealerSupply
    {
        // 1. Idempotency Check
        if (! empty($data['idempotency_key']) && $this->hasTableColumn('dealer_supplies', 'idempotency_key')) {
            $existing = DealerSupply::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing->load(['dealer', 'items.product', 'payments']);
            }
        }

        $dealer = Dealer::findOrFail($data['dealer_id']);
        $supplyDate = ! empty($data['supply_date'])
            ? Carbon::parse($data['supply_date'], 'Asia/Kolkata')->toDateString()
            : Carbon::today('Asia/Kolkata')->toDateString();

        return DB::transaction(function () use ($dealer, $supplyDate, $data, $user) {
            $companyId = $dealer->company_id ?? $user?->company_id ?? Auth::user()?->company_id;
            // Generate unique Supply No — serialized with lock and global sequence check
            // to support both table-wide unique constraint and multi-tenant scoping safely.
            $dateSlug = Carbon::parse($supplyDate)->format('Ymd');
            $supplyPrefix = sprintf('DS-%s-', $dateSlug);

            // Fetch the highest sequence across ALL companies for this dateSlug prefix
            // so it can NEVER violate the table-wide supplies_supply_no_unique constraint.
            $lastSupply = DealerSupply::withoutGlobalScopes()
                ->withTrashed()
                ->where('supply_no', 'like', $supplyPrefix . '%')
                ->lockForUpdate()
                ->orderByDesc('supply_no')
                ->first(['supply_no']);

            $lastSupplySeq = 0;
            if ($lastSupply) {
                $parts = explode('-', $lastSupply->supply_no);
                $lastSupplySeq = (int) end($parts);
            }

            do {
                $lastSupplySeq++;
                $supplyNo = $supplyPrefix . sprintf('%04d', $lastSupplySeq);
            } while (
                DealerSupply::withoutGlobalScopes()
                    ->withTrashed()
                    ->where('supply_no', $supplyNo)
                    ->exists()
            );

            $discountAmount = isset($data['discount_amount']) ? (float) $data['discount_amount'] : 0.00;
            $adjustmentAmount = isset($data['adjustment_amount']) ? (float) $data['adjustment_amount'] : 0.00;

            $supply = DealerSupply::create($this->filterAttributesForTable('dealer_supplies', [
                'company_id' => $companyId,
                'supply_no' => $supplyNo,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'dealer_id' => $dealer->id,
                'supply_date' => $supplyDate,
                'subtotal' => 0.00,
                'tax_amount' => 0.00,
                'discount_amount' => $discountAmount,
                'adjustment_amount' => $adjustmentAmount,
                'total_amount' => 0.00,
                'paid_amount' => 0.00,
                'outstanding_amount' => 0.00,
                'payment_status' => 'unpaid',
                'status' => 'delivered',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]));

            $subtotal = 0.00;
            $taxTotal = 0.00;

            // Process line items with price snapshots
            foreach ($data['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                if ($product->company_id && (int) $product->company_id !== (int) $companyId) {
                    throw ValidationException::withMessages([
                        'items' => ["Product '{$product->name}' does not belong to the active company."],
                    ]);
                }

                $quantity = (float) $itemData['quantity'];
                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Quantity for product '{$product->name}' must be greater than zero."],
                    ]);
                }

                // Resolve dealer-specific contract price effective on supply date
                $priceInfo = $dealer->getEffectiveProductPrice($product->id, $supplyDate);
                $catalogPrice = (float) $priceInfo['catalog_price'];

                // Custom applied price override if explicitly specified in request, otherwise contract/catalog
                $appliedPrice = isset($itemData['custom_price']) && is_numeric($itemData['custom_price'])
                    ? (float) $itemData['custom_price']
                    : (float) $priceInfo['price'];

                $lineTotal = round($quantity * $appliedPrice, 2);
                $taxRate = 0.00;
                $lineTax = 0.00;

                $subtotal += $lineTotal;
                $taxTotal += $lineTax;

                DealerSupplyItem::create($this->filterAttributesForTable('dealer_supply_items', [
                    'company_id' => $companyId,
                    'dealer_supply_id' => $supply->id,
                    'supply_id' => $supply->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit' => $itemData['unit'] ?? $product->unit,
                    'catalog_price' => $catalogPrice,
                    'applied_price' => $appliedPrice,
                    'unit_price' => $appliedPrice,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                    'total_amount' => $lineTotal,
                    'notes' => $itemData['notes'] ?? null,
                ]));
            }

            $totalAmount = round($subtotal + $adjustmentAmount - $discountAmount, 2);
            $supply->subtotal = $subtotal;
            $supply->tax_amount = 0.00;
            $supply->total_amount = $totalAmount;
            if ($this->hasTableColumn('dealer_supplies', 'outstanding_amount')) {
                $supply->outstanding_amount = $totalAmount;
            }
            $supply->save();

            // Process immediate collection if provided
            if (isset($data['payment_amount']) && (float) $data['payment_amount'] > 0) {
                $payAmount = (float) $data['payment_amount'];
                $payDate = ! empty($data['payment_date'])
                    ? Carbon::parse($data['payment_date'], 'Asia/Kolkata')->toDateString()
                    : $supplyDate;

                // Generate unique Payment No — serialized with FOR UPDATE lock to prevent race conditions
                $payDateSlug = Carbon::parse($payDate)->format('Ymd');
                $payPrefix = sprintf('PAY-%s-', $payDateSlug);

                $lastPayment = DealerPayment::withoutGlobalScopes()
                    ->withTrashed()
                    ->where('payment_no', 'like', $payPrefix . '%')
                    ->lockForUpdate()
                    ->orderByDesc('payment_no')
                    ->first(['payment_no']);

                $lastPaySeq = 0;
                if ($lastPayment) {
                    $parts = explode('-', $lastPayment->payment_no);
                    $lastPaySeq = (int) end($parts);
                }

                do {
                    $lastPaySeq++;
                    $paymentNo = $payPrefix . sprintf('%04d', $lastPaySeq);
                } while (
                    DealerPayment::withoutGlobalScopes()
                        ->withTrashed()
                        ->where('payment_no', $paymentNo)
                        ->exists()
                );

                $includeInLedger = true;
                if (array_key_exists('include_in_ledger_amount', $data)) {
                    $includeInLedger = filter_var($data['include_in_ledger_amount'], FILTER_VALIDATE_BOOLEAN);
                }

                $payIdempotencyKey = isset($data['idempotency_key']) ? $data['idempotency_key'].'-PAY' : null;
                if ($payIdempotencyKey && $this->hasTableColumn('dealer_payments', 'idempotency_key')) {
                    if (DealerPayment::withoutGlobalScopes()->withTrashed()->where('idempotency_key', $payIdempotencyKey)->exists()) {
                        $payIdempotencyKey .= '-'.substr(uniqid(), -4);
                    }
                }

                $payment = DealerPayment::create($this->filterAttributesForTable('dealer_payments', [
                    'company_id' => $companyId,
                    'payment_no' => $paymentNo,
                    'idempotency_key' => $payIdempotencyKey,
                    'dealer_id' => $dealer->id,
                    'dealer_supply_id' => $supply->id,
                    'payment_date' => $payDate,
                    'amount' => $payAmount,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'payment_mode' => $data['payment_method'] ?? 'cash',
                    'reference_no' => $data['payment_reference'] ?? null,
                    'status' => 'completed',
                    'remarks' => $data['payment_notes'] ?? null,
                    'notes' => $data['payment_notes'] ?? null,
                    'include_in_ledger_amount' => $includeInLedger,
                    'collected_by' => $user?->id,
                    'created_by' => $user?->id,
                ]));

                // Synchronize payment credit ledger entry
                $payment->syncLedgerEntry($user?->id);

                // Update supply paid amount (capped at supply's total amount)
                $supplyPaid = min($totalAmount, $payAmount);
                if ($this->hasTableColumn('dealer_supplies', 'paid_amount')) {
                    $supply->paid_amount = $supplyPaid;
                }
                if ($this->hasTableColumn('dealer_supplies', 'outstanding_amount')) {
                    $supply->outstanding_amount = round(max(0.0, $totalAmount - $payAmount), 2);
                }
                if ($this->hasTableColumn('dealer_supplies', 'payment_status')) {
                    if ($payAmount >= $totalAmount) {
                        $supply->payment_status = 'paid';
                    } elseif ($payAmount > 0) {
                        $supply->payment_status = 'partially_paid';
                    }
                }
                $supply->save();
            }

            // Synchronize supply debit ledger entry
            $supply->syncLedgerEntry($user?->id);

            // Record stock issue only for products configured for stock tracking
            app(InventoryService::class)->recordSupplyStockIssue($supply, $user);

            return $supply->load(['dealer', 'items.product', 'payments']);
        });
    }

    /**
     * Updates an existing supply transaction and recalculates financials & ledger.
     */
    public function updateSupply(DealerSupply $supply, array $data, ?User $user = null): DealerSupply
    {
        return DB::transaction(function () use ($supply, $data, $user) {
            $dealer = $supply->dealer;
            $supplyDate = ! empty($data['supply_date'])
                ? Carbon::parse($data['supply_date'], 'Asia/Kolkata')->toDateString()
                : $supply->supply_date->toDateString();

            $supply->supply_date = $supplyDate;
            if (isset($data['notes'])) {
                $supply->notes = $data['notes'];
            }
            if (isset($data['discount_amount'])) {
                $supply->discount_amount = (float) $data['discount_amount'];
            }
            if (isset($data['adjustment_amount'])) {
                $supply->adjustment_amount = (float) $data['adjustment_amount'];
            }
            $supply->updated_by = $user?->id;

            // If replacement items provided
            if (! empty($data['items']) && is_array($data['items'])) {
                // Reverse previous stock issues for tracked items
                app(InventoryService::class)->reverseSupplyStockIssue($supply, $user);
                $supply->items()->delete();

                $subtotal = 0.00;
                $taxTotal = 0.00;

                $companyId = $supply->company_id ?? $dealer->company_id ?? $user?->company_id;
                foreach ($data['items'] as $itemData) {
                    $product = Product::findOrFail($itemData['product_id']);
                    if ($product->company_id && (int) $product->company_id !== (int) $companyId) {
                        throw ValidationException::withMessages([
                            'items' => ["Product '{$product->name}' does not belong to the active company."],
                        ]);
                    }

                    $quantity = (float) $itemData['quantity'];
                    if ($quantity <= 0) {
                        throw ValidationException::withMessages([
                            'items' => ["Quantity for product '{$product->name}' must be greater than zero."],
                        ]);
                    }

                    $priceInfo = $dealer->getEffectiveProductPrice($product->id, $supplyDate);
                    $catalogPrice = (float) $priceInfo['catalog_price'];
                    $appliedPrice = isset($itemData['custom_price']) && is_numeric($itemData['custom_price'])
                        ? (float) $itemData['custom_price']
                        : (float) $priceInfo['price'];

                    $lineTotal = round($quantity * $appliedPrice, 2);
                    $taxRate = 0.00;
                    $lineTax = 0.00;

                    $subtotal += $lineTotal;
                    $taxTotal += $lineTax;

                    DealerSupplyItem::create($this->filterAttributesForTable('dealer_supply_items', [
                        'company_id' => $companyId,
                        'dealer_supply_id' => $supply->id,
                        'supply_id' => $supply->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit' => $itemData['unit'] ?? $product->unit,
                        'catalog_price' => $catalogPrice,
                        'applied_price' => $appliedPrice,
                        'unit_price' => $appliedPrice,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $lineTax,
                        'line_total' => $lineTotal,
                        'total_amount' => $lineTotal,
                        'notes' => $itemData['notes'] ?? null,
                    ]));
                }

                $supply->subtotal = $subtotal;
                $supply->tax_amount = 0.00;
            }

            $supply->recalculateFinancials(true);
            $supply->syncLedgerEntry($user?->id);

            // If supply date changed, synchronize linked payment dates as well
            if (! empty($data['supply_date'])) {
                foreach ($supply->payments as $linkedPayment) {
                    $linkedPayment->payment_date = $supplyDate;
                    $linkedPayment->save();
                    $linkedPayment->syncLedgerEntry($user?->id);
                }
            }

            // Re-record stock issue for updated items (tracked items only)
            if (! empty($data['items']) && is_array($data['items'])) {
                app(InventoryService::class)->recordSupplyStockIssue($supply, $user);
            }

            return $supply->load(['dealer', 'items.product', 'payments']);
        });
    }

    /**
     * Delete an existing supply transaction and reverse its inventory and ledger impacts.
     */
    public function deleteSupply(DealerSupply $supply, ?User $user = null): bool
    {
        return DB::transaction(function () use ($supply, $user) {
            // 1. Reverse stock issues if tracked
            app(InventoryService::class)->reverseSupplyStockIssue($supply, $user);

            // 2. Delete ledger entry for the supply
            $supply->ledgerEntry()?->delete();

            // 3. Delete linked payments and their ledger entries
            foreach ($supply->payments as $payment) {
                $payment->ledgerEntry()?->delete();
                $payment->delete();
            }

            // 4. Delete items
            $supply->items()->delete();

            // 5. Delete supply record
            return (bool) $supply->delete();
        });
    }

    /**
     * Records a collection payment (standalone or linked to a supply).
     */
    public function recordPayment(array $data, ?User $user = null): DealerPayment
    {
        // Idempotency check
        if (! empty($data['idempotency_key'])) {
            $existing = DealerPayment::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing->load(['dealer', 'supply']);
            }
        }

        $dealer = Dealer::findOrFail($data['dealer_id']);
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount must be greater than zero.'],
            ]);
        }

        $paymentDate = ! empty($data['payment_date'])
            ? Carbon::parse($data['payment_date'], 'Asia/Kolkata')->toDateString()
            : Carbon::today('Asia/Kolkata')->toDateString();

        return DB::transaction(function () use ($dealer, $amount, $paymentDate, $data, $user) {
            $companyId = $dealer->company_id ?? $user?->company_id ?? Auth::user()?->company_id;
            // Generate unique Payment No — serialized with FOR UPDATE lock to prevent race conditions
            $dateSlug = Carbon::parse($paymentDate)->format('Ymd');
            $payPrefix = sprintf('PAY-%s-', $dateSlug);

            $lastPayment = DealerPayment::withoutGlobalScopes()
                ->withTrashed()
                ->where('payment_no', 'like', $payPrefix . '%')
                ->lockForUpdate()
                ->orderByDesc('payment_no')
                ->first(['payment_no']);

            $lastPaySeq = 0;
            if ($lastPayment) {
                $parts = explode('-', $lastPayment->payment_no);
                $lastPaySeq = (int) end($parts);
            }

            do {
                $lastPaySeq++;
                $paymentNo = $payPrefix . sprintf('%04d', $lastPaySeq);
            } while (
                DealerPayment::withoutGlobalScopes()
                    ->withTrashed()
                    ->where('payment_no', $paymentNo)
                    ->exists()
            );

            $includeInLedger = true;
            if (array_key_exists('include_in_ledger_amount', $data)) {
                $includeInLedger = filter_var($data['include_in_ledger_amount'], FILTER_VALIDATE_BOOLEAN);
            }

            $payment = DealerPayment::create($this->filterAttributesForTable('dealer_payments', [
                'company_id' => $companyId,
                'payment_no' => $paymentNo,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'dealer_id' => $dealer->id,
                'dealer_supply_id' => $data['dealer_supply_id'] ?? null,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'payment_mode' => $data['payment_method'] ?? 'cash',
                'reference_no' => $data['reference_no'] ?? null,
                'status' => 'completed',
                'remarks' => $data['notes'] ?? ($data['remarks'] ?? null),
                'notes' => $data['notes'] ?? ($data['remarks'] ?? null),
                'include_in_ledger_amount' => $includeInLedger,
                'collected_by' => $user?->id,
                'created_by' => $user?->id,
            ]));

            // If linked to a supply, update that supply's payment financials
            if (! empty($data['dealer_supply_id'])) {
                $supply = DealerSupply::find($data['dealer_supply_id']);
                if ($supply) {
                    $supply->recalculateFinancials(true);
                }
            }

            // Sync ledger immediately
            $payment->syncLedgerEntry($user?->id);

            return $payment->load(['dealer', 'supply']);
        });
    }

    /**
     * Update an existing payment record and synchronize ledger and supply.
     */
    public function updatePayment(DealerPayment $payment, array $data, ?User $user = null): DealerPayment
    {
        return DB::transaction(function () use ($payment, $data, $user) {
            if (! empty($data['payment_date'])) {
                $payment->payment_date = Carbon::parse($data['payment_date'], 'Asia/Kolkata')->toDateString();
            }
            if (isset($data['payment_method']) || isset($data['payment_mode'])) {
                $method = $data['payment_method'] ?? $data['payment_mode'];
                $payment->payment_method = $method;
                $payment->payment_mode = $method;
            }
            if (array_key_exists('reference_no', $data)) {
                $payment->reference_no = $data['reference_no'];
            }
            if (array_key_exists('remarks', $data)) {
                $payment->remarks = $data['remarks'];
            }
            if (array_key_exists('notes', $data)) {
                $payment->notes = $data['notes'];
            }
            if (isset($data['amount']) && (float) $data['amount'] > 0) {
                $payment->amount = (float) $data['amount'];
            }
            if (array_key_exists('include_in_ledger_amount', $data)) {
                $payment->include_in_ledger_amount = filter_var($data['include_in_ledger_amount'], FILTER_VALIDATE_BOOLEAN);
            }
            $payment->updated_by = $user?->id;
            $payment->save();

            // Sync ledger entry
            $payment->syncLedgerEntry($user?->id);

            // If linked to supply, update supply financials
            if ($payment->dealer_supply_id) {
                $supply = $payment->supply;
                $supply?->recalculateFinancials(true);
            }

            return $payment->load(['dealer', 'supply']);
        });
    }

    /**
     * Delete an existing payment record and reverse ledger entries and supply amounts.
     */
    public function deletePayment(DealerPayment $payment, ?User $user = null): bool
    {
        return DB::transaction(function () use ($payment, $user) {
            // Delete ledger entry
            $payment->ledgerEntry()?->delete();

            $supplyId = $payment->dealer_supply_id;

            // Delete payment
            $deleted = $payment->delete();

            // If linked to supply, recalculate supply financials
            if ($supplyId) {
                $supply = DealerSupply::find($supplyId);
                $supply?->recalculateFinancials(true);
            }

            return (bool) $deleted;
        });
    }

    /**
     * Real-time daily summary for the selected date.
     */
    public function getDailySummary(string $targetDate): array
    {
        $date = Carbon::parse($targetDate, 'Asia/Kolkata')->toDateString();

        // 1. Supply metrics for date
        $supplies = DealerSupply::whereDate('supply_date', $date)
            ->where('status', 'delivered')
            ->get();

        $totalSuppliesCount = $supplies->count();
        $totalSuppliesAmount = (float) $supplies->sum('total_amount');
        $totalPaidOnSupplies = (float) $supplies->sum('paid_amount');
        $totalOutstandingOnSupplies = (float) $supplies->sum('outstanding_amount');

        $unpaidCount = $supplies->where('payment_status', 'unpaid')->count();
        $partiallyPaidCount = $supplies->where('payment_status', 'partially_paid')->count();
        $paidCount = $supplies->where('payment_status', 'paid')->count();

        // Total units delivered
        $supplyIds = $supplies->pluck('id');
        $totalQuantity = (float) DealerSupplyItem::whereIn('dealer_supply_id', $supplyIds)->sum('quantity');

        // 2. Collection metrics for date
        $payments = DealerPayment::whereDate('payment_date', $date)
            ->where('status', 'completed')
            ->get();

        $totalCollectionsAmount = (float) $payments->sum('amount');
        $totalCollectionsCount = $payments->count();

        $cashAmount = (float) $payments->where('payment_method', 'cash')->sum('amount');
        $upiAmount = (float) $payments->where('payment_method', 'upi')->sum('amount');
        $chequeAmount = (float) $payments->where('payment_method', 'cheque')->sum('amount');
        $bankTransferAmount = (float) $payments->whereIn('payment_method', ['bank_transfer', 'neft'])->sum('amount');

        // 3. Active dealers served
        $supplyDealerIds = $supplies->pluck('dealer_id')->toArray();
        $paymentDealerIds = $payments->pluck('dealer_id')->toArray();
        $uniqueDealerIds = array_unique(array_merge($supplyDealerIds, $paymentDealerIds));
        $dealersServedCount = count($uniqueDealerIds);

        return [
            'date' => $date,
            'timezone' => 'Asia/Kolkata',
            'supplies' => [
                'total_count' => $totalSuppliesCount,
                'total_amount' => round($totalSuppliesAmount, 2),
                'total_quantity' => round($totalQuantity, 2),
                'paid_amount' => round($totalPaidOnSupplies, 2),
                'outstanding_amount' => round($totalOutstandingOnSupplies, 2),
                'unpaid_count' => $unpaidCount,
                'partially_paid_count' => $partiallyPaidCount,
                'paid_count' => $paidCount,
            ],
            'collections' => [
                'total_count' => $totalCollectionsCount,
                'total_amount' => round($totalCollectionsAmount, 2),
                'by_method' => [
                    'cash' => round($cashAmount, 2),
                    'upi' => round($upiAmount, 2),
                    'cheque' => round($chequeAmount, 2),
                    'bank_transfer' => round($bankTransferAmount, 2),
                ],
            ],
            'dealers_served_count' => $dealersServedCount,
        ];
    }

    /**
     * Returns list of dealers annotated with their current running outstanding balance
     * and their supply & payment status on the selected target date.
     */
    public function getDailyDealers(string $targetDate, ?string $search = null, ?string $statusFilter = null)
    {
        $date = Carbon::parse($targetDate, 'Asia/Kolkata')->toDateString();

        $query = Dealer::query()
            ->where('is_active', true)
            ->with([
                'supplies' => fn ($q) => $q->whereDate('supply_date', $date)->where('status', 'delivered')->with('items.product'),
                'payments' => fn ($q) => $q->whereDate('payment_date', $date)->where('status', 'completed'),
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        $dealers = $query->orderBy('name')->get();

        $mapped = $dealers->map(function ($dealer) {
            $todaySupplies = $dealer->supplies;
            $todayPayments = $dealer->payments;
            $todayPaidSum = (float) $todayPayments->sum('amount');

            $hasSupply = $todaySupplies->isNotEmpty();
            $latestSupply = $todaySupplies->sortByDesc('id')->first();

            $todayStatus = 'no_supply';
            $todaySupplyData = null;

            if ($hasSupply && $latestSupply) {
                $totalSupplyAmount = (float) $todaySupplies->sum('total_amount');
                $totalSupplyPaid = (float) $todaySupplies->sum('paid_amount');
                $totalSupplyOutstanding = (float) $todaySupplies->sum('outstanding_amount');
                $totalItemsCount = $todaySupplies->reduce(fn ($c, $s) => $c + $s->items->count(), 0);

                if ($totalSupplyOutstanding <= 0.0001) {
                    $todayStatus = 'paid';
                } elseif ($totalSupplyPaid > 0) {
                    $todayStatus = 'partially_paid';
                } else {
                    $todayStatus = 'unpaid';
                }

                $supplyNoDisplay = $todaySupplies->count() > 1
                    ? "{$todaySupplies->count()} Supplies"
                    : $latestSupply->supply_no;

                $todaySupplyData = [
                    'id' => $latestSupply->id,
                    'supply_no' => $supplyNoDisplay,
                    'total_amount' => round($totalSupplyAmount, 2),
                    'paid_amount' => round($totalSupplyPaid, 2),
                    'outstanding_amount' => round($totalSupplyOutstanding, 2),
                    'payment_status' => $todayStatus,
                    'items_count' => $totalItemsCount,
                ];
            }

            return [
                'id' => $dealer->id,
                'code' => $dealer->code,
                'name' => $dealer->name,
                'phone' => $dealer->phone,
                'contact_person' => $dealer->contact_person,
                'billing_address' => $dealer->address,
                'credit_limit' => (float) $dealer->credit_limit,
                'current_outstanding' => $dealer->calculateOutstandingBalance(),
                'has_supply_today' => $hasSupply,
                'today_status' => $todayStatus,
                'today_supply' => $todaySupplyData,
                'today_collected_amount' => round($todayPaidSum, 2),
            ];
        });

        if ($statusFilter) {
            if ($statusFilter === 'supplied') {
                $mapped = $mapped->filter(fn ($d) => $d['has_supply_today'])->values();
            } elseif ($statusFilter === 'pending') {
                $mapped = $mapped->filter(fn ($d) => in_array($d['today_status'], ['unpaid', 'partially_paid']))->values();
            } elseif ($statusFilter === 'paid') {
                $mapped = $mapped->filter(fn ($d) => $d['today_status'] === 'paid')->values();
            } elseif ($statusFilter === 'no_supply') {
                $mapped = $mapped->filter(fn ($d) => ! $d['has_supply_today'])->values();
            }
        }

        return $mapped;
    }
}
