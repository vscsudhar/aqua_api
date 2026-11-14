<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\DamagedProduct;
use App\Models\DealerSupply;
use App\Models\Product;
use App\Models\Purchase;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BottleLedgerService
{
    /**
     * Standard 8 product keys required for Bottle Ledger.
     */
    public const PRODUCT_KEYS = [
        '1L',
        '2L',
        '5L',
        '500ML',
        '200ML',
        '100ML',
        'NBT',
        'BOT',
    ];

    /**
     * Resolve product name/SKU to one of the 8 standard bottle keys.
     * Excludes refills and non-bottle products.
     */
    public function resolveProductKey(?string $name, ?string $sku = '', ?string $productType = null): ?string
    {
        // Strictly exclude Refills by product_type
        if ($productType === 'refill') {
            return null;
        }

        $str = strtoupper(trim(($name ?? '') . ' ' . ($sku ?? '')));

        if (empty($str)) {
            return null;
        }

        // Strictly exclude Refills by name
        if (strpos($str, 'REFILL') !== false || strpos($str, 'REFIL') !== false) {
            return null;
        }

        // 20L jars/cans are refill containers unless explicitly named bottle
        if (preg_match('/\b20\s*L\b|\b20-L\b|\b20L\b|\b20\s*LIT/i', $str) && (strpos($str, 'BOTTLE') === false)) {
            return null;
        }

        // Exclude caps, seals, raw materials unless they match NBT or BOT
        if (strpos($str, 'CAPS') !== false || strpos($str, 'SEAL') !== false) {
            return null;
        }

        // Check 100ML
        if (preg_match('/100\s*ML/i', $str)) {
            return '100ML';
        }

        // Check 200ML
        if (preg_match('/200\s*ML/i', $str)) {
            return '200ML';
        }

        // Check 500ML (or 300ML / 250ML packaged water bottle)
        if (preg_match('/500\s*ML/i', $str) || preg_match('/300\s*ML/i', $str) || preg_match('/250\s*ML/i', $str)) {
            return '500ML';
        }

        // Check 5L / 5 Litre
        if (preg_match('/\b5\s*(?:L|LITER|LITRE)\b/i', $str)) {
            return '5L';
        }

        // Check 2L / 2 Litre
        if (preg_match('/\b2\s*(?:L|LITER|LITRE)\b/i', $str)) {
            return '2L';
        }

        // Check 1L / 1 Litre
        if (preg_match('/\b1\s*(?:L|LITER|LITRE)\b/i', $str)) {
            return '1L';
        }

        // Check NBT (New Bottle / Non-refill Bottle Type)
        if (preg_match('/\bNBT\b/i', $str)) {
            return 'NBT';
        }

        // Check BOT (Bottom / Bottle)
        if (preg_match('/\bBOT\b|\bBOTTOM\b/i', $str)) {
            return 'BOT';
        }

        return null;
    }

    /**
     * Build the monthly matrix and stock register for the company.
     */
    public function getMonthlyLedger(int $companyId, int $year, int $month): array
    {
        $startDate = Carbon::createFromDate($year, $month, 1, 'Asia/Kolkata')->startOfDay();
        $daysInMonth = $startDate->daysInMonth;
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();
        $monthName = $startDate->format('F Y');

        $company = Company::find($companyId);
        $companyName = $company?->name ?? 'Marina Aqua';

        // 1. Calculate opening stock for each product key prior to $startDate
        $priorSupplierQtys = $this->getCumulativeSupplierQuantities($companyId, $startDate->toDateString());
        $priorDealerQtys = $this->getCumulativeDealerQuantities($companyId, $startDate->toDateString());
        $priorDamagedQtys = $this->getCumulativeDamagedQuantities($companyId, $startDate->toDateString());

        $openingStock = [];
        foreach (self::PRODUCT_KEYS as $key) {
            $in = $priorSupplierQtys[$key] ?? 0.0;
            $out = ($priorDealerQtys[$key] ?? 0.0) + ($priorDamagedQtys[$key] ?? 0.0);
            $openingStock[$key] = round(max(0, $in - $out), 2);
        }

        // 2. Fetch all dealer supplies for the month
        $supplies = DealerSupply::with(['items.product', 'dealer'])
            ->where('company_id', $companyId)
            ->where('status', 'delivered')
            ->whereBetween('supply_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // 3. Fetch all supplier purchases for the month
        $purchases = Purchase::with(['items.product', 'supplier'])
            ->where('company_id', $companyId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('purchase_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // 3b. Fetch all damaged products for the month
        $damagedEntries = DamagedProduct::with('product')
            ->where('company_id', $companyId)
            ->whereBetween('damage_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // 4. Initialize daily maps
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
                'products' => array_fill_keys(self::PRODUCT_KEYS, 0.0),
                'quantities' => array_fill_keys(self::PRODUCT_KEYS, 0.0),
                'total_amount' => 0.0,
            ];

            $supplierDaily[$dateStr] = [
                'day' => $dayCode,
                'date' => $dateStr,
                'day_name' => $dateCarbon->format('D'),
                'products' => array_fill_keys(self::PRODUCT_KEYS, 0.0),
                'quantities' => array_fill_keys(self::PRODUCT_KEYS, 0.0),
                'total_amount' => 0.0,
            ];

            $damageDaily[$dateStr] = [
                'day' => $dayCode,
                'date' => $dateStr,
                'day_name' => $dateCarbon->format('D'),
                'products' => array_fill_keys(self::PRODUCT_KEYS, 0.0),
                'quantities' => array_fill_keys(self::PRODUCT_KEYS, 0.0),
                'total_amount' => 0.0,
            ];
        }

        // 5. Populate Dealer Purchases WITHOUT REFILL
        foreach ($supplies as $supply) {
            $dateStr = Carbon::parse($supply->supply_date)->toDateString();
            if (! isset($dealerDaily[$dateStr])) {
                continue;
            }

            foreach ($supply->items as $item) {
                $pName = $item->product?->name ?? '';
                $pSku = $item->product?->sku ?? '';
                $pType = $item->product?->product_type ?? null;
                $key = $this->resolveProductKey($pName, $pSku, $pType);

                if (! $key) {
                    continue; // Skip refill items or unmapped products
                }

                $amt = (float) ($item->total_amount ?? $item->line_total ?? ($item->quantity * $item->unit_price));
                $qty = (float) $item->quantity;

                $dealerDaily[$dateStr]['products'][$key] += $amt;
                $dealerDaily[$dateStr]['quantities'][$key] += $qty;
                $dealerDaily[$dateStr]['total_amount'] += $amt;
            }
        }

        // 6. Populate Supplier Purchases
        foreach ($purchases as $purchase) {
            $dateStr = Carbon::parse($purchase->purchase_date)->toDateString();
            if (! isset($supplierDaily[$dateStr])) {
                continue;
            }

            foreach ($purchase->items as $item) {
                $pName = $item->product_name ?? $item->product?->name ?? '';
                $pSku = $item->product_sku ?? $item->product?->sku ?? '';
                $key = $this->resolveProductKey($pName, $pSku);

                if (! $key) {
                    continue;
                }

                $amt = (float) ($item->total_cost ?: ($item->quantity * $item->unit_cost));
                $qty = (float) $item->quantity;

                $supplierDaily[$dateStr]['products'][$key] += $amt;
                $supplierDaily[$dateStr]['quantities'][$key] += $qty;
                $supplierDaily[$dateStr]['total_amount'] += $amt;
            }
        }

        // 6b. Populate Damaged Bottle Ledger Outward
        foreach ($damagedEntries as $dmg) {
            $dateStr = Carbon::parse($dmg->damage_date)->toDateString();
            if (! isset($damageDaily[$dateStr])) {
                continue;
            }

            $pName = $dmg->product_name . ' ' . ($dmg->variant ?? '');
            $pSku = $dmg->product?->sku ?? '';
            $key = $this->resolveProductKey($pName, $pSku);

            if (! $key) {
                continue;
            }

            $amt = (float) $dmg->total_cost;
            $qty = (float) $dmg->quantity;

            $damageDaily[$dateStr]['products'][$key] += $amt;
            $damageDaily[$dateStr]['quantities'][$key] += $qty;
            $damageDaily[$dateStr]['total_amount'] += $amt;
        }

        // 7. Calculate Monthly Totals for Dealer Table
        $dealerMonthlyTotals = array_fill_keys(self::PRODUCT_KEYS, 0.0);
        $dealerMonthlyQtyTotals = array_fill_keys(self::PRODUCT_KEYS, 0.0);
        $dealerGrandTotal = 0.0;

        foreach ($dealerDaily as $dayData) {
            foreach (self::PRODUCT_KEYS as $k) {
                $dealerMonthlyTotals[$k] += $dayData['products'][$k];
                $dealerMonthlyQtyTotals[$k] += $dayData['quantities'][$k];
            }
            $dealerGrandTotal += $dayData['total_amount'];
        }

        // 8. Calculate Monthly Totals for Supplier Table
        $supplierMonthlyTotals = array_fill_keys(self::PRODUCT_KEYS, 0.0);
        $supplierMonthlyQtyTotals = array_fill_keys(self::PRODUCT_KEYS, 0.0);
        $supplierGrandTotal = 0.0;

        foreach ($supplierDaily as $dayData) {
            foreach (self::PRODUCT_KEYS as $k) {
                $supplierMonthlyTotals[$k] += $dayData['products'][$k];
                $supplierMonthlyQtyTotals[$k] += $dayData['quantities'][$k];
            }
            $supplierGrandTotal += $dayData['total_amount'];
        }

        // 8b. Calculate Monthly Totals for Damage Table
        $damageMonthlyTotals = array_fill_keys(self::PRODUCT_KEYS, 0.0);
        $damageMonthlyQtyTotals = array_fill_keys(self::PRODUCT_KEYS, 0.0);
        $damageGrandTotal = 0.0;

        foreach ($damageDaily as $dayData) {
            foreach (self::PRODUCT_KEYS as $k) {
                $damageMonthlyTotals[$k] += $dayData['products'][$k];
                $damageMonthlyQtyTotals[$k] += $dayData['quantities'][$k];
            }
            $damageGrandTotal += $dayData['total_amount'];
        }

        // 9. Stock Register: Closing = Opening + Inward - Outward - Damaged
        $stockRegister = [];
        foreach (self::PRODUCT_KEYS as $k) {
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
                'product_keys' => self::PRODUCT_KEYS,
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
    protected function getCumulativeSupplierQuantities(int $companyId, string $beforeDate): array
    {
        $totals = array_fill_keys(self::PRODUCT_KEYS, 0.0);

        $purchases = Purchase::with(['items.product'])
            ->where('company_id', $companyId)
            ->where('status', '!=', 'cancelled')
            ->where('purchase_date', '<', $beforeDate)
            ->get();

        foreach ($purchases as $pur) {
            foreach ($pur->items as $item) {
                $pName = $item->product_name ?? $item->product?->name ?? '';
                $pSku = $item->product_sku ?? $item->product?->sku ?? '';
                $key = $this->resolveProductKey($pName, $pSku);
                if ($key) {
                    $totals[$key] += (float) $item->quantity;
                }
            }
        }

        return $totals;
    }

    /**
     * Cumulative dealer outward quantities before a date.
     */
    protected function getCumulativeDealerQuantities(int $companyId, string $beforeDate): array
    {
        $totals = array_fill_keys(self::PRODUCT_KEYS, 0.0);

        $supplies = DealerSupply::with(['items.product'])
            ->where('company_id', $companyId)
            ->where('status', 'delivered')
            ->where('supply_date', '<', $beforeDate)
            ->get();

        foreach ($supplies as $sup) {
            foreach ($sup->items as $item) {
                $pName = $item->product?->name ?? '';
                $pSku = $item->product?->sku ?? '';
                $pType = $item->product?->product_type ?? null;
                $key = $this->resolveProductKey($pName, $pSku, $pType);
                if ($key) {
                    $totals[$key] += (float) $item->quantity;
                }
            }
        }

        return $totals;
    }

    /**
     * Cumulative damaged bottle quantities before a date.
     */
    protected function getCumulativeDamagedQuantities(int $companyId, string $beforeDate): array
    {
        $totals = array_fill_keys(self::PRODUCT_KEYS, 0.0);

        $damaged = DamagedProduct::with('product')
            ->where('company_id', $companyId)
            ->where('damage_date', '<', $beforeDate)
            ->get();

        foreach ($damaged as $dmg) {
            $pName = $dmg->product_name . ' ' . ($dmg->variant ?? '');
            $pSku = $dmg->product?->sku ?? '';
            $key = $this->resolveProductKey($pName, $pSku);
            if ($key) {
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

        if ($source === 'dealer') {
            $supplies = DealerSupply::with(['items.product', 'dealer'])
                ->where('company_id', $companyId)
                ->where('status', 'delivered')
                ->whereDate('supply_date', $date)
                ->get();

            foreach ($supplies as $supply) {
                foreach ($supply->items as $item) {
                    $pName = $item->product?->name ?? '';
                    $pSku = $item->product?->sku ?? '';
                    $pType = $item->product?->product_type ?? null;
                    $resolved = $this->resolveProductKey($pName, $pSku, $pType);

                    if ($resolved === $productKey) {
                        $transactions[] = [
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
        } elseif ($source === 'supplier') {
            $purchases = Purchase::with(['items.product', 'supplier'])
                ->where('company_id', $companyId)
                ->where('status', '!=', 'cancelled')
                ->whereDate('purchase_date', $date)
                ->get();

            foreach ($purchases as $purchase) {
                foreach ($purchase->items as $item) {
                    $pName = $item->product_name ?? $item->product?->name ?? '';
                    $pSku = $item->product_sku ?? $item->product?->sku ?? '';
                    $resolved = $this->resolveProductKey($pName, $pSku);

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
                $pName = $dmg->product_name . ' ' . ($dmg->variant ?? '');
                $pSku = $dmg->product?->sku ?? '';
                $resolved = $this->resolveProductKey($pName, $pSku);

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
     * Get a base64 encoded data URI for the company logo, resized to fit PDF headers.
     * Prevents single-threaded loopback deadlocks and eliminates Dompdf memory exhaustion.
     */
    public function getPdfLogoBase64(?Company $company, int $maxW = 240, int $maxH = 80): ?string
    {
        if (! $company) {
            return null;
        }

        $logoPath = $company->logo_path;
        if (! $logoPath || ! Storage::disk('public')->exists($logoPath)) {
            return null;
        }

        $absPath = Storage::disk('public')->path($logoPath);
        if (! file_exists($absPath)) {
            return null;
        }

        try {
            $info = @getimagesize($absPath);
            if (! $info) {
                return null;
            }

            $origW = $info[0];
            $origH = $info[1];
            $mime = $info['mime'] ?? 'image/png';

            // If already small enough (under 240x80 and < 100KB), return directly
            if ($origW <= $maxW && $origH <= $maxH && filesize($absPath) < 102400) {
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absPath));
            }

            // Downsample with GD to protect memory and render instantly in Dompdf
            $src = null;
            if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                $src = @imagecreatefromjpeg($absPath);
            } elseif ($mime === 'image/png') {
                $src = @imagecreatefrompng($absPath);
            } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
                $src = @imagecreatefromwebp($absPath);
            }

            if (! $src) {
                return null;
            }

            $scale = min($maxW / $origW, $maxH / $origH, 1.0);
            $newW = max(1, (int) round($origW * $scale));
            $newH = max(1, (int) round($origH * $scale));

            $thumb = imagecreatetruecolor($newW, $newH);
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefilledrectangle($thumb, 0, 0, $newW, $newH, $transparent);

            imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

            ob_start();
            imagepng($thumb, null, 6);
            $thumbBytes = ob_get_clean();

            imagedestroy($thumb);
            imagedestroy($src);

            if ($thumbBytes) {
                return 'data:image/png;base64,' . base64_encode($thumbBytes);
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed generating PDF logo thumbnail: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Generate PDF view for Bottle Ledger in A4 landscape.
     */
    public function generatePdf(int $companyId, int $year, int $month)
    {
        $company = Company::find($companyId);
        $data = $this->getMonthlyLedger($companyId, $year, $month);

        // Replace remote logo URL with local optimized base64 thumbnail
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
}

