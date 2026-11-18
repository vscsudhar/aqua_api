<?php

namespace App\Services;

use App\Models\BottleOtherEntry;
use App\Models\Company;
use App\Models\DamagedProduct;
use App\Models\DealerSupply;
use App\Models\DealerSupplyItem;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BottleLedgerService
{
    /**
     * Fetch all eligible bottle products for the specified company from Product Master.
     * Strictly excludes Refill products.
     */
    public function getCompanyBottleProducts(int $companyId)
    {
        return Product::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('for_invoice')
                    ->orWhere('for_invoice', false);
            })
            ->where(function ($q) {
                $q->whereNull('product_type')
                    ->orWhereRaw('LOWER(TRIM(product_type)) != ?', ['refill']);
            })
            ->whereRaw('LOWER(TRIM(name)) NOT LIKE ?', ['%refill%'])
            ->whereRaw('LOWER(TRIM(name)) NOT LIKE ?', ['%refil%'])
            ->whereRaw('LOWER(TRIM(COALESCE(sku, ""))) NOT LIKE ?', ['%refill%'])
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Resolves an item's product to the corresponding Product Master key name for this company.
     * Strictly excludes refills.
     */
    public function resolveProductKeyForCompany(
        ?int $productId,
        ?string $name,
        ?string $sku = '',
        ?string $productType = null,
        array $idToKey = [],
        array $nameToKey = [],
        array $skuToKey = []
    ): ?string {
        // 1. Strictly exclude Refills by product_type (case-insensitive, trimmed)
        if ($productType && strtolower(trim($productType)) === 'refill') {
            return null;
        }

        $n = strtoupper(trim($name ?? ''));
        $s = strtoupper(trim($sku ?? ''));

        // Strictly exclude Refills by name or SKU
        if (strpos($n, 'REFILL') !== false || strpos($n, 'REFIL') !== false || strpos($s, 'REFILL') !== false) {
            return null;
        }

        // 20L jars/cans are refill containers unless explicitly named bottle
        if (preg_match('/\b20\s*L\b|\b20-L\b|\b20L\b|\b20\s*LIT/i', $n) && strpos($n, 'BOTTLE') === false) {
            return null;
        }

        // 2. Direct ID match
        if ($productId && isset($idToKey[$productId])) {
            return $idToKey[$productId];
        }

        // 3. Direct Name match
        if ($n && isset($nameToKey[$n])) {
            return $nameToKey[$n];
        }

        // 4. Direct SKU match
        if ($s && isset($skuToKey[$s])) {
            return $skuToKey[$s];
        }

        // 5. Fuzzy match against registered product keys
        foreach ($nameToKey as $canonUpper => $canonName) {
            if ($n === $canonUpper || ($n && strpos($n, $canonUpper) !== false) || ($s && $s === $canonUpper)) {
                return $canonName;
            }
        }

        return null;
    }

    /**
     * Build the dynamic monthly matrix and stock register for the company.
     */
    public function getMonthlyLedger(int $companyId, int $year, int $month): array
    {
        $startDate = Carbon::createFromDate($year, $month, 1, 'Asia/Kolkata')->startOfDay();
        $daysInMonth = $startDate->daysInMonth;
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();
        $monthName = $startDate->format('F Y');

        $company = Company::find($companyId);
        $companyName = $company?->name ?? 'SS AquaFlow';

        // 1. Fetch dynamic products belonging ONLY to this company (excluding refills)
        $products = $this->getCompanyBottleProducts($companyId);
        $productKeys = $products->pluck('name')->unique()->values()->toArray();

        // Build lookup maps for fast and accurate resolution
        $idToKey = [];
        $nameToKey = [];
        $skuToKey = [];
        foreach ($products as $p) {
            $idToKey[$p->id] = $p->name;
            $nameToKey[strtoupper(trim($p->name))] = $p->name;
            if (! empty($p->sku)) {
                $skuToKey[strtoupper(trim($p->sku))] = $p->name;
            }
        }

        // 2. Calculate opening stock for each product key prior to $startDate
        $priorSupplierQtys = $this->getCumulativeSupplierQuantities($companyId, $startDate->toDateString(), $productKeys, $idToKey, $nameToKey, $skuToKey);
        $priorDealerQtys = $this->getCumulativeDealerQuantities($companyId, $startDate->toDateString(), $productKeys, $idToKey, $nameToKey, $skuToKey);
        $priorDamagedQtys = $this->getCumulativeDamagedQuantities($companyId, $startDate->toDateString(), $productKeys, $idToKey, $nameToKey, $skuToKey);

        $openingStock = [];
        foreach ($productKeys as $key) {
            $in = $priorSupplierQtys[$key] ?? 0.0;
            $out = ($priorDealerQtys[$key] ?? 0.0) + ($priorDamagedQtys[$key] ?? 0.0);
            $openingStock[$key] = round(max(0, $in - $out), 2);
        }

        // 3. Fetch dealer supplies for the month
        $supplies = DealerSupply::with(['items.product', 'dealer'])
            ->where('company_id', $companyId)
            ->where('status', 'delivered')
            ->whereBetween('supply_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // 4. Fetch supplier purchases for the month
        $purchases = Purchase::with(['items.product', 'supplier'])
            ->where('company_id', $companyId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('purchase_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // 5. Fetch damaged products for the month
        $damagedEntries = DamagedProduct::with('product')
            ->where('company_id', $companyId)
            ->whereBetween('damage_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // 5b. Fetch bottle other entries for the month
        $othersEntries = BottleOtherEntry::with('product')
            ->where('company_id', $companyId)
            ->whereBetween('entry_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // 6. Initialize daily maps
        $dealerDaily = [];
        $supplierDaily = [];
        $damageDaily = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateCarbon = Carbon::createFromDate($year, $month, $d, 'Asia/Kolkata');
            $dateStr = $dateCarbon->toDateString();
            $dayCode = sprintf('%02d', $d);

            $dealerDaily[$dateStr] = [
                'day' => $dayCode,
                'date' => $dateStr,
                'day_name' => $dateCarbon->format('D'),
                'products' => array_fill_keys($productKeys, 0.0),
                'quantities' => array_fill_keys($productKeys, 0.0),
                'total_amount' => 0.0,
            ];

            $supplierDaily[$dateStr] = [
                'day' => $dayCode,
                'date' => $dateStr,
                'day_name' => $dateCarbon->format('D'),
                'products' => array_fill_keys($productKeys, 0.0),
                'quantities' => array_fill_keys($productKeys, 0.0),
                'total_amount' => 0.0,
            ];

            $damageDaily[$dateStr] = [
                'day' => $dayCode,
                'date' => $dateStr,
                'day_name' => $dateCarbon->format('D'),
                'products' => array_fill_keys($productKeys, 0.0),
                'quantities' => array_fill_keys($productKeys, 0.0),
                'total_amount' => 0.0,
            ];
        }

        // 7. Populate Dealer Purchases WITHOUT REFILL
        foreach ($supplies as $supply) {
            $dateStr = Carbon::parse($supply->supply_date)->toDateString();
            if (! isset($dealerDaily[$dateStr])) {
                continue;
            }

            foreach ($supply->items as $item) {
                $pId = $item->product_id;
                $pName = $item->product?->name ?? '';
                $pSku = $item->product?->sku ?? '';
                $pType = $item->product?->product_type ?? null;

                $key = $this->resolveProductKeyForCompany($pId, $pName, $pSku, $pType, $idToKey, $nameToKey, $skuToKey);
                if (! $key || ! in_array($key, $productKeys, true)) {
                    continue;
                }

                $amt = (float) ($item->total_amount ?? $item->line_total ?? ($item->quantity * $item->unit_price));
                $qty = (float) $item->quantity;

                $dealerDaily[$dateStr]['products'][$key] += $amt;
                $dealerDaily[$dateStr]['quantities'][$key] += $qty;
                $dealerDaily[$dateStr]['total_amount'] += $amt;
            }
        }

        // 7b. Populate Bottle Others Entries into Dealer Bottle Purchase table
        foreach ($othersEntries as $oth) {
            $dateStr = Carbon::parse($oth->entry_date)->toDateString();
            if (! isset($dealerDaily[$dateStr])) {
                continue;
            }

            $pId = $oth->product_id;
            $pName = $oth->product?->name ?? '';
            $pSku = $oth->product?->sku ?? '';
            $pType = $oth->product?->product_type ?? null;

            $key = $this->resolveProductKeyForCompany($pId, $pName, $pSku, $pType, $idToKey, $nameToKey, $skuToKey);
            if (! $key || ! in_array($key, $productKeys, true)) {
                continue;
            }

            $amt = (float) $oth->total_amount;
            $qty = (float) $oth->quantity;

            $dealerDaily[$dateStr]['products'][$key] += $amt;
            $dealerDaily[$dateStr]['quantities'][$key] += $qty;
            $dealerDaily[$dateStr]['total_amount'] += $amt;
        }

        // 8. Populate Supplier Purchases
        foreach ($purchases as $purchase) {
            $dateStr = Carbon::parse($purchase->purchase_date)->toDateString();
            if (! isset($supplierDaily[$dateStr])) {
                continue;
            }

            foreach ($purchase->items as $item) {
                $pId = $item->product_id;
                $pName = $item->product_name ?? $item->product?->name ?? '';
                $pSku = $item->product_sku ?? $item->product?->sku ?? '';
                $pType = $item->product?->product_type ?? null;

                $key = $this->resolveProductKeyForCompany($pId, $pName, $pSku, $pType, $idToKey, $nameToKey, $skuToKey);
                if (! $key || ! in_array($key, $productKeys, true)) {
                    continue;
                }

                $amt = (float) ($item->total_cost ?: ($item->quantity * $item->unit_cost));
                $qty = (float) $item->quantity;

                $supplierDaily[$dateStr]['products'][$key] += $amt;
                $supplierDaily[$dateStr]['quantities'][$key] += $qty;
                $supplierDaily[$dateStr]['total_amount'] += $amt;
            }
        }

        // 9. Populate Damaged Bottle Ledger Outward
        foreach ($damagedEntries as $dmg) {
            $dateStr = Carbon::parse($dmg->damage_date)->toDateString();
            if (! isset($damageDaily[$dateStr])) {
                continue;
            }

            $pId = $dmg->product_id;
            $pName = $dmg->product_name . ' ' . ($dmg->variant ?? '');
            $pSku = $dmg->product?->sku ?? '';
            $pType = $dmg->product?->product_type ?? null;

            $key = $this->resolveProductKeyForCompany($pId, $pName, $pSku, $pType, $idToKey, $nameToKey, $skuToKey);
            if (! $key || ! in_array($key, $productKeys, true)) {
                continue;
            }

            $amt = (float) $dmg->total_cost;
            $qty = (float) $dmg->quantity;

            $damageDaily[$dateStr]['products'][$key] += $amt;
            $damageDaily[$dateStr]['quantities'][$key] += $qty;
            $damageDaily[$dateStr]['total_amount'] += $amt;
        }

        // 10. Calculate Monthly Totals for Dealer Table
        $dealerMonthlyTotals = array_fill_keys($productKeys, 0.0);
        $dealerMonthlyQtyTotals = array_fill_keys($productKeys, 0.0);
        $dealerGrandTotal = 0.0;

        foreach ($dealerDaily as $dayData) {
            foreach ($productKeys as $k) {
                $dealerMonthlyTotals[$k] += $dayData['products'][$k];
                $dealerMonthlyQtyTotals[$k] += $dayData['quantities'][$k];
            }
            $dealerGrandTotal += $dayData['total_amount'];
        }

        // 11. Calculate Monthly Totals for Supplier Table
        $supplierMonthlyTotals = array_fill_keys($productKeys, 0.0);
        $supplierMonthlyQtyTotals = array_fill_keys($productKeys, 0.0);
        $supplierGrandTotal = 0.0;

        foreach ($supplierDaily as $dayData) {
            foreach ($productKeys as $k) {
                $supplierMonthlyTotals[$k] += $dayData['products'][$k];
                $supplierMonthlyQtyTotals[$k] += $dayData['quantities'][$k];
            }
            $supplierGrandTotal += $dayData['total_amount'];
        }

        // 12. Calculate Monthly Totals for Damage Table
        $damageMonthlyTotals = array_fill_keys($productKeys, 0.0);
        $damageMonthlyQtyTotals = array_fill_keys($productKeys, 0.0);
        $damageGrandTotal = 0.0;

        foreach ($damageDaily as $dayData) {
            foreach ($productKeys as $k) {
                $damageMonthlyTotals[$k] += $dayData['products'][$k];
                $damageMonthlyQtyTotals[$k] += $dayData['quantities'][$k];
            }
            $damageGrandTotal += $dayData['total_amount'];
        }

        // 13. Stock Register: Closing = Opening + Inward - Outward - Damaged
        $stockRegister = [];
        foreach ($productKeys as $k) {
            $op = $openingStock[$k] ?? 0.0;
            $in = $supplierMonthlyQtyTotals[$k] ?? 0.0;
            $out = $dealerMonthlyQtyTotals[$k] ?? 0.0;
            $dmg = $damageMonthlyQtyTotals[$k] ?? 0.0;
            $cl = round($op + $in - $out - $dmg, 2);

            $stockRegister[] = [
                'product_key' => $k,
                'opening_qty' => $op,
                'supplier_inward_qty' => $in,
                'dealer_outward_qty' => $out,
                'damaged_outward_qty' => $dmg,
                'closing_qty' => $cl,
            ];
        }

        // Round numeric values in daily rows
        $dealerRows = array_values(array_map(function ($row) {
            $rowQtyTotal = 0.0;
            foreach ($row['products'] as $k => $v) {
                $row['products'][$k] = round($v, 2);
                $rowQtyTotal += (float) ($row['quantities'][$k] ?? 0.0);
            }
            $row['total_amount'] = round($row['total_amount'], 2);
            $row['total_quantity'] = round($rowQtyTotal, 2);
            return $row;
        }, $dealerDaily));

        $supplierRows = array_values(array_map(function ($row) {
            $rowQtyTotal = 0.0;
            foreach ($row['products'] as $k => $v) {
                $row['products'][$k] = round($v, 2);
                $rowQtyTotal += (float) ($row['quantities'][$k] ?? 0.0);
            }
            $row['total_amount'] = round($row['total_amount'], 2);
            $row['total_quantity'] = round($rowQtyTotal, 2);
            return $row;
        }, $supplierDaily));

        $damageRows = array_values(array_map(function ($row) {
            $rowQtyTotal = 0.0;
            foreach ($row['products'] as $k => $v) {
                $row['products'][$k] = round($v, 2);
                $rowQtyTotal += (float) ($row['quantities'][$k] ?? 0.0);
            }
            $row['total_amount'] = round($row['total_amount'], 2);
            $row['total_quantity'] = round($rowQtyTotal, 2);
            return $row;
        }, $damageDaily));

        // Round totals
        $dealerGrandQtyTotal = 0.0;
        foreach ($dealerMonthlyTotals as $k => $v) {
            $dealerMonthlyTotals[$k] = round($v, 2);
            $dealerMonthlyQtyTotals[$k] = round($dealerMonthlyQtyTotals[$k] ?? 0.0, 2);
            $dealerGrandQtyTotal += $dealerMonthlyQtyTotals[$k];
        }
        $supplierGrandQtyTotal = 0.0;
        foreach ($supplierMonthlyTotals as $k => $v) {
            $supplierMonthlyTotals[$k] = round($v, 2);
            $supplierMonthlyQtyTotals[$k] = round($supplierMonthlyQtyTotals[$k] ?? 0.0, 2);
            $supplierGrandQtyTotal += $supplierMonthlyQtyTotals[$k];
        }
        $damageGrandQtyTotal = 0.0;
        foreach ($damageMonthlyTotals as $k => $v) {
            $damageMonthlyTotals[$k] = round($v, 2);
            $damageMonthlyQtyTotals[$k] = round($damageMonthlyQtyTotals[$k] ?? 0.0, 2);
            $damageGrandQtyTotal += $damageMonthlyQtyTotals[$k];
        }

        return [
            'meta' => [
                'company_name' => $companyName,
                'company_logo' => $company?->logo_url,
                'year' => $year,
                'month' => $month,
                'month_name' => $monthName,
                'days_in_month' => $daysInMonth,
                'product_keys' => $productKeys,
            ],
            'opening_stock' => $openingStock,
            'dealer_table' => [
                'title' => 'Dealer Bottle Purchase (WITHOUT REFILL)',
                'rows' => $dealerRows,
                'monthly_totals' => $dealerMonthlyTotals,
                'monthly_qty_totals' => $dealerMonthlyQtyTotals,
                'grand_total' => round($dealerGrandTotal, 2),
                'grand_qty_total' => round($dealerGrandQtyTotal, 2),
            ],
            'supplier_table' => [
                'title' => 'Supplier Bottle Purchase',
                'rows' => $supplierRows,
                'monthly_totals' => $supplierMonthlyTotals,
                'monthly_qty_totals' => $supplierMonthlyQtyTotals,
                'grand_total' => round($supplierGrandTotal, 2),
                'grand_qty_total' => round($supplierGrandQtyTotal, 2),
            ],
            'damage_table' => [
                'title' => 'Damage Bottle Ledger',
                'rows' => $damageRows,
                'monthly_totals' => $damageMonthlyTotals,
                'monthly_qty_totals' => $damageMonthlyQtyTotals,
                'grand_total' => round($damageGrandTotal, 2),
                'grand_qty_total' => round($damageGrandQtyTotal, 2),
            ],
            'stock_register' => $stockRegister,
        ];
    }

    /**
     * Cumulative supplier inward quantities before a date.
     */
    protected function getCumulativeSupplierQuantities(
        int $companyId,
        string $beforeDate,
        array $productKeys,
        array $idToKey,
        array $nameToKey,
        array $skuToKey
    ): array {
        $totals = array_fill_keys($productKeys, 0.0);

        $purchases = Purchase::with(['items.product'])
            ->where('company_id', $companyId)
            ->where('status', '!=', 'cancelled')
            ->where('purchase_date', '<', $beforeDate)
            ->get();

        foreach ($purchases as $pur) {
            foreach ($pur->items as $item) {
                $pId = $item->product_id;
                $pName = $item->product_name ?? $item->product?->name ?? '';
                $pSku = $item->product_sku ?? $item->product?->sku ?? '';
                $pType = $item->product?->product_type ?? null;

                $key = $this->resolveProductKeyForCompany($pId, $pName, $pSku, $pType, $idToKey, $nameToKey, $skuToKey);
                if ($key && isset($totals[$key])) {
                    $totals[$key] += (float) $item->quantity;
                }
            }
        }

        return $totals;
    }

    /**
     * Cumulative dealer outward quantities before a date.
     */
    protected function getCumulativeDealerQuantities(
        int $companyId,
        string $beforeDate,
        array $productKeys,
        array $idToKey,
        array $nameToKey,
        array $skuToKey
    ): array {
        $totals = array_fill_keys($productKeys, 0.0);

        $supplies = DealerSupply::with(['items.product'])
            ->where('company_id', $companyId)
            ->where('status', 'delivered')
            ->where('supply_date', '<', $beforeDate)
            ->get();

        foreach ($supplies as $sup) {
            foreach ($sup->items as $item) {
                $pId = $item->product_id;
                $pName = $item->product?->name ?? '';
                $pSku = $item->product?->sku ?? '';
                $pType = $item->product?->product_type ?? null;

                $key = $this->resolveProductKeyForCompany($pId, $pName, $pSku, $pType, $idToKey, $nameToKey, $skuToKey);
                if ($key && isset($totals[$key])) {
                    $totals[$key] += (float) $item->quantity;
                }
            }
        }

        $priorOthers = BottleOtherEntry::with(['product'])
            ->where('company_id', $companyId)
            ->where('entry_date', '<', $beforeDate)
            ->get();

        foreach ($priorOthers as $oth) {
            $pId = $oth->product_id;
            $pName = $oth->product?->name ?? '';
            $pSku = $oth->product?->sku ?? '';
            $pType = $oth->product?->product_type ?? null;

            $key = $this->resolveProductKeyForCompany($pId, $pName, $pSku, $pType, $idToKey, $nameToKey, $skuToKey);
            if ($key && isset($totals[$key])) {
                $totals[$key] += (float) $oth->quantity;
            }
        }

        return $totals;
    }

    /**
     * Cumulative damaged bottle quantities before a date.
     */
    protected function getCumulativeDamagedQuantities(
        int $companyId,
        string $beforeDate,
        array $productKeys,
        array $idToKey,
        array $nameToKey,
        array $skuToKey
    ): array {
        $totals = array_fill_keys($productKeys, 0.0);

        $damaged = DamagedProduct::with('product')
            ->where('company_id', $companyId)
            ->where('damage_date', '<', $beforeDate)
            ->get();

        foreach ($damaged as $dmg) {
            $pId = $dmg->product_id;
            $pName = $dmg->product_name . ' ' . ($dmg->variant ?? '');
            $pSku = $dmg->product?->sku ?? '';
            $pType = $dmg->product?->product_type ?? null;

            $key = $this->resolveProductKeyForCompany($pId, $pName, $pSku, $pType, $idToKey, $nameToKey, $skuToKey);
            if ($key && isset($totals[$key])) {
                $totals[$key] += (float) $dmg->quantity;
            }
        }

        return $totals;
    }

    /**
     * Get underlying transactions for a daily cell drill-down.
     */
    public function getTransactionDetails(int $companyId, string $date, string $productKey, string $source): array
    {
        $transactions = [];

        $products = $this->getCompanyBottleProducts($companyId);
        $idToKey = [];
        $nameToKey = [];
        $skuToKey = [];
        foreach ($products as $p) {
            $idToKey[$p->id] = $p->name;
            $nameToKey[strtoupper(trim($p->name))] = $p->name;
            if (! empty($p->sku)) {
                $skuToKey[strtoupper(trim($p->sku))] = $p->name;
            }
        }

        if ($source === 'dealer') {
            $supplies = DealerSupply::with(['items.product', 'dealer'])
                ->where('company_id', $companyId)
                ->where('status', 'delivered')
                ->whereDate('supply_date', $date)
                ->get();

            foreach ($supplies as $supply) {
                foreach ($supply->items as $item) {
                    $pId = $item->product_id;
                    $pName = $item->product?->name ?? '';
                    $pSku = $item->product?->sku ?? '';
                    $pType = $item->product?->product_type ?? null;
                    $resolved = $this->resolveProductKeyForCompany($pId, $pName, $pSku, $pType, $idToKey, $nameToKey, $skuToKey);

                    if ($resolved === $productKey) {
                        $transactions[] = [
                            'id' => (int) $item->id,
                            'supply_id' => (int) $supply->id,
                            'entry_type' => 'dealer_supply_item',
                            'party_name' => $supply->dealer?->name ?? 'Dealer #'.$supply->dealer_id,
                            'reference_no' => $supply->supply_no,
                            'date' => Carbon::parse($supply->supply_date)->format('d/m/Y'),
                            'product_key' => $productKey,
                            'product_name' => $pName ?: $productKey,
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'total_amount' => (float) ($item->total_amount ?? $item->line_total ?? ($item->quantity * $item->unit_price)),
                            'notes' => $supply->notes ?? '',
                        ];
                    }
                }
            }

            $others = BottleOtherEntry::with('product')
                ->where('company_id', $companyId)
                ->whereDate('entry_date', $date)
                ->get();

            foreach ($others as $oth) {
                $pId = $oth->product_id;
                $pName = $oth->product?->name ?? '';
                $pSku = $oth->product?->sku ?? '';
                $pType = $oth->product?->product_type ?? null;
                $resolved = $this->resolveProductKeyForCompany($pId, $pName, $pSku, $pType, $idToKey, $nameToKey, $skuToKey);

                if ($resolved === $productKey) {
                    $transactions[] = [
                        'id' => (int) $oth->id,
                        'supply_id' => null,
                        'entry_type' => 'others_entry',
                        'party_name' => 'Others Entry' . ($oth->notes ? ' (' . $oth->notes . ')' : ''),
                        'reference_no' => 'OTH-' . $oth->id,
                        'date' => Carbon::parse($oth->entry_date)->format('d/m/Y'),
                        'product_key' => $productKey,
                        'product_name' => $pName ?: $productKey,
                        'quantity' => (float) $oth->quantity,
                        'unit_price' => (float) $oth->amount,
                        'total_amount' => (float) $oth->total_amount,
                        'notes' => $oth->notes ?? 'Others Bottle Entry',
                    ];
                }
            }
        } elseif ($source === 'supplier') {
            $purchases = Purchase::with(['items.product', 'supplier'])
                ->where('company_id', $companyId)
                ->where('status', '!=', 'cancelled')
                ->whereDate('purchase_date', $date)
                ->get();

            foreach ($purchases as $purchase) {
                foreach ($purchase->items as $item) {
                    $pId = $item->product_id;
                    $pName = $item->product_name ?? $item->product?->name ?? '';
                    $pSku = $item->product_sku ?? $item->product?->sku ?? '';
                    $pType = $item->product?->product_type ?? null;
                    $resolved = $this->resolveProductKeyForCompany($pId, $pName, $pSku, $pType, $idToKey, $nameToKey, $skuToKey);

                    if ($resolved === $productKey) {
                        $transactions[] = [
                            'party_name' => $purchase->supplier_name ?? $purchase->supplier?->name ?? 'Supplier #'.$purchase->supplier_id,
                            'reference_no' => $purchase->invoice_no ?: $purchase->purchase_no,
                            'date' => Carbon::parse($purchase->purchase_date)->format('d/m/Y'),
                            'product_key' => $productKey,
                            'product_name' => $pName ?: $productKey,
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_cost,
                            'total_amount' => (float) ($item->total_cost ?: ($item->quantity * $item->unit_cost)),
                            'notes' => $purchase->notes ?? '',
                        ];
                    }
                }
            }
        } elseif ($source === 'damage') {
            $damaged = DamagedProduct::with('product')
                ->where('company_id', $companyId)
                ->whereDate('damage_date', $date)
                ->get();

            foreach ($damaged as $dmg) {
                $pId = $dmg->product_id;
                $pName = $dmg->product_name . ' ' . ($dmg->variant ?? '');
                $pSku = $dmg->product?->sku ?? '';
                $pType = $dmg->product?->product_type ?? null;
                $resolved = $this->resolveProductKeyForCompany($pId, $pName, $pSku, $pType, $idToKey, $nameToKey, $skuToKey);

                if ($resolved === $productKey) {
                    $transactions[] = [
                        'party_name' => 'Damage Out (' . $dmg->reason . ')',
                        'reference_no' => $dmg->damage_no,
                        'date' => Carbon::parse($dmg->damage_date)->format('d/m/Y'),
                        'product_key' => $productKey,
                        'product_name' => $dmg->product_name . ($dmg->variant ? " ({$dmg->variant})" : ''),
                        'quantity' => (float) $dmg->quantity,
                        'unit_price' => (float) $dmg->unit_cost,
                        'total_amount' => (float) $dmg->total_cost,
                        'notes' => $dmg->remarks ?: $dmg->reason,
                    ];
                }
            }
        }

        return $transactions;
    }

    /**
     * Get the company logo URL / path for PDF reports.
     */
    public function getPdfLogoBase64(?Company $company, int $maxW = 240, int $maxH = 80): ?string
    {
        if (! $company) {
            return null;
        }

        if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
            return Storage::disk('public')->path($company->logo_path);
        }

        return $company->logo_url;
    }

    /**
     * Generate PDF view for Bottle Ledger in A4 landscape.
     */
    public function generatePdf(int $companyId, int $year, int $month)
    {
        $company = Company::find($companyId);
        $data = $this->getMonthlyLedger($companyId, $year, $month);

        $data['meta']['company_logo'] = $this->getPdfLogoBase64($company);

        $pdf = Pdf::loadView('reports.bottle_ledger_pdf', $data);
        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'chroot' => [public_path(), base_path(), storage_path()],
            'defaultFont' => 'Noto Sans',
        ]);

        return $pdf;
    }

    /**
     * Get eligible Without Refill products for dropdown.
     */
    public function getWithoutRefillProducts(int $companyId)
    {
        return $this->getCompanyBottleProducts($companyId)->map(function ($p) {
            return [
                'id' => (int) $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'product_type' => $p->product_type,
                'default_price' => (float) ($p->default_price ?: $p->cost_price ?: 0.0),
            ];
        })->values();
    }

    /**
     * Fetch Others statement date-wise and individual entries for the month.
     */
    public function getOthersStatement(int $companyId, int $year, int $month): array
    {
        $startDate = Carbon::createFromDate($year, $month, 1, 'Asia/Kolkata')->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();
        $monthName = $startDate->format('F Y');

        $company = Company::find($companyId);
        $companyName = $company?->name ?? 'SS AquaFlow';

        $entries = BottleOtherEntry::with('product')
            ->where('company_id', $companyId)
            ->whereBetween('entry_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('entry_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $dateGroups = [];
        $totalCount = 0;
        $grandTotalAmount = 0.0;

        foreach ($entries as $entry) {
            $dStr = Carbon::parse($entry->entry_date)->toDateString();
            if (! isset($dateGroups[$dStr])) {
                $dateGroups[$dStr] = [
                    'date' => $dStr,
                    'formatted_date' => Carbon::parse($entry->entry_date)->format('d-m-Y'),
                    'day_name' => Carbon::parse($entry->entry_date)->format('D'),
                    'bottle_count' => 0,
                    'amount' => 0.0,
                    'entries' => [],
                ];
            }

            $qty = (int) $entry->quantity;
            $amt = (float) $entry->total_amount;

            $dateGroups[$dStr]['bottle_count'] += $qty;
            $dateGroups[$dStr]['amount'] += $amt;
            $dateGroups[$dStr]['entries'][] = [
                'id' => (int) $entry->id,
                'date' => $dStr,
                'formatted_date' => Carbon::parse($entry->entry_date)->format('d-m-Y'),
                'product_id' => (int) $entry->product_id,
                'product_name' => $entry->product?->name ?? 'Product #'.$entry->product_id,
                'product_sku' => $entry->product?->sku ?? '',
                'quantity' => $qty,
                'amount' => (float) $entry->amount,
                'total_amount' => $amt,
                'notes' => $entry->notes ?? '',
            ];

            $totalCount += $qty;
            $grandTotalAmount += $amt;
        }

        foreach ($dateGroups as &$dg) {
            $dg['amount'] = round($dg['amount'], 2);
        }
        unset($dg);

        $flatEntries = [];
        foreach ($entries as $e) {
            $flatEntries[] = [
                'id' => (int) $e->id,
                'date' => Carbon::parse($e->entry_date)->toDateString(),
                'formatted_date' => Carbon::parse($e->entry_date)->format('d-m-Y'),
                'product_id' => (int) $e->product_id,
                'product_name' => $e->product?->name ?? 'Product #'.$e->product_id,
                'product_sku' => $e->product?->sku ?? '',
                'quantity' => (int) $e->quantity,
                'amount' => (float) $e->amount,
                'total_amount' => (float) $e->total_amount,
                'notes' => $e->notes ?? '',
            ];
        }

        return [
            'company_name' => $companyName,
            'company_logo' => $this->getPdfLogoBase64($company),
            'year' => $year,
            'month' => $month,
            'month_name' => $monthName,
            'statement' => array_values($dateGroups),
            'total_bottle_count' => $totalCount,
            'grand_total_amount' => round($grandTotalAmount, 2),
            'entries' => $flatEntries,
        ];
    }

    /**
     * Create a new Bottle Other Entry.
     */
    public function createOtherEntry(int $companyId, array $data, ?int $userId): BottleOtherEntry
    {
        $qty = (int) $data['quantity'];
        $amt = (float) $data['amount'];
        $total = round($qty * $amt, 2);

        return BottleOtherEntry::create([
            'company_id' => $companyId,
            'entry_date' => Carbon::parse($data['entry_date'])->toDateString(),
            'product_id' => $data['product_id'],
            'quantity' => $qty,
            'amount' => $amt,
            'total_amount' => $total,
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * Update an existing Bottle Other Entry.
     */
    public function updateOtherEntry(int $companyId, int $id, array $data, ?int $userId): BottleOtherEntry
    {
        $entry = BottleOtherEntry::where('company_id', $companyId)->findOrFail($id);

        $qty = isset($data['quantity']) ? (int) $data['quantity'] : $entry->quantity;
        $amt = isset($data['amount']) ? (float) $data['amount'] : $entry->amount;
        $total = round($qty * $amt, 2);

        $entry->update([
            'entry_date' => isset($data['entry_date']) ? Carbon::parse($data['entry_date'])->toDateString() : $entry->entry_date,
            'product_id' => $data['product_id'] ?? $entry->product_id,
            'quantity' => $qty,
            'amount' => $amt,
            'total_amount' => $total,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $entry->notes,
            'updated_by' => $userId,
        ]);

        return $entry;
    }

    /**
     * Soft delete an existing Bottle Other Entry.
     */
    public function deleteOtherEntry(int $companyId, int $id): bool
    {
        $entry = BottleOtherEntry::where('company_id', $companyId)->findOrFail($id);
        return (bool) $entry->delete();
    }

    /**
     * Generate PDF view for Others Statement in A4 portrait.
     */
    public function generateOthersStatementPdf(int $companyId, int $year, int $month)
    {
        $data = $this->getOthersStatement($companyId, $year, $month);

        $pdf = Pdf::loadView('reports.bottle_others_statement_pdf', $data);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'chroot' => [public_path(), base_path(), storage_path()],
            'defaultFont' => 'Noto Sans',
        ]);

        return $pdf;
    }

    /**
     * Delete a bottle purchase entry (Dealer Supply Item or Others Entry)
     * and synchronize Daily Collection, Other Statement, and Bottle Ledger.
     */
    public function deleteBottlePurchaseEntry(int $companyId, string $entryType, int $id, ?User $user = null): array
    {
        return DB::transaction(function () use ($companyId, $entryType, $id, $user) {
            if ($entryType === 'others_entry') {
                $entry = BottleOtherEntry::where('company_id', $companyId)->findOrFail($id);

                $deletedData = [
                    'id' => $entry->id,
                    'entry_type' => 'others_entry',
                    'date' => Carbon::parse($entry->entry_date)->toDateString(),
                    'product_id' => $entry->product_id,
                    'quantity' => (int) $entry->quantity,
                    'amount' => (float) $entry->amount,
                    'total_amount' => (float) $entry->total_amount,
                ];

                $entry->delete();

                return [
                    'success' => true,
                    'message' => 'Others bottle purchase entry deleted successfully.',
                    'deleted' => $deletedData,
                ];
            }

            if ($entryType === 'dealer_supply_item') {
                $item = DealerSupplyItem::with(['supply.dealer', 'product'])->findOrFail($id);
                $supply = $item->supply;

                if (! $supply || (int) $supply->company_id !== (int) $companyId) {
                    throw new \Exception('Supply item does not belong to the active company.');
                }

                $deletedData = [
                    'id' => $item->id,
                    'supply_id' => $supply->id,
                    'supply_no' => $supply->supply_no,
                    'entry_type' => 'dealer_supply_item',
                    'date' => Carbon::parse($supply->supply_date)->toDateString(),
                    'product_id' => $item->product_id,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total_amount' => (float) ($item->total_amount ?? $item->line_total ?? ($item->quantity * $item->unit_price)),
                ];

                $remainingItemsCount = $supply->items()->where('id', '!=', $item->id)->count();

                if ($remainingItemsCount === 0) {
                    // This was the only item in the supply: delete the full supply and linked records
                    app(DailyCollectionService::class)->deleteSupply($supply, $user);
                } else {
                    // Supply has other items remaining: reverse stock issue for this item, delete item, recalculate supply
                    $mov = InventoryMovement::where('reference_type', 'dealer_supply')
                        ->where('reference_id', $supply->id)
                        ->where('product_id', $item->product_id)
                        ->where('movement_type', 'supply_out')
                        ->first();

                    if ($mov) {
                        $prod = Product::find($mov->product_id);
                        if ($prod && $prod->track_stock) {
                            app(InventoryService::class)->recordMovement(
                                productOrId: $prod,
                                movementType: 'supply_out',
                                quantity: (float) $item->quantity,
                                signedQuantity: (float) $item->quantity, // return back to stock
                                referenceType: 'dealer_supply',
                                referenceId: $supply->id,
                                notes: "Stock returned from bottle purchase deletion in supply: #{$supply->supply_no}",
                                userId: $user?->id,
                            );
                        }
                        if ($mov->quantity <= $item->quantity) {
                            $mov->delete();
                        } else {
                            $mov->quantity = (float) ($mov->quantity - $item->quantity);
                            $mov->save();
                        }
                    }

                    // Delete the item
                    $item->delete();

                    // Recalculate supply subtotal and total amount
                    $newSubtotal = (float) $supply->items()->sum('total_amount');
                    $supply->subtotal = $newSubtotal;
                    $supply->total_amount = round(max(0.0, $newSubtotal + $supply->adjustment_amount - $supply->discount_amount), 2);
                    $supply->outstanding_amount = round(max(0.0, $supply->total_amount - $supply->paid_amount), 2);

                    if ($supply->paid_amount >= $supply->total_amount && $supply->total_amount > 0) {
                        $supply->payment_status = 'paid';
                    } elseif ($supply->paid_amount > 0) {
                        $supply->payment_status = 'partially_paid';
                    } else {
                        $supply->payment_status = 'unpaid';
                    }

                    $supply->save();

                    // Resynchronize supply debit ledger entry
                    $supply->syncLedgerEntry($user?->id);
                }

                return [
                    'success' => true,
                    'message' => 'Dealer bottle purchase entry deleted successfully and Daily Collection synchronized.',
                    'deleted' => $deletedData,
                ];
            }

            throw new \InvalidArgumentException("Invalid entry type '{$entryType}'. Must be 'dealer_supply_item' or 'others_entry'.");
        });
    }
}
