<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\DailyOtherTransaction;
use App\Models\Dealer;
use App\Models\DealerPayment;
use App\Models\DealerSupply;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DealerLedgerReportService
{
    /**
     * Determines if a product is a 20L / refill water cane (Dealer Ledger = With Refill only).
     * Considers product_type == 'refill' (even if word 'refill' is missing in the name),
     * or product name contains 'refill' (even if product_type is missing/normal).
     */
    public function isRefillCaneProduct(
        ?int $pId,
        string $prodName,
        string $prodSku = '',
        string $prodUnit = '',
        ?int $caneProductId = null,
        ?string $productType = null
    ): bool {
        // 1. Explicit cane product ID match
        if ($caneProductId && $pId == $caneProductId) {
            return true;
        }

        // 2. Explicit product_type == 'refill' (takes priority even if refill word is missing in name)
        if ($productType === 'refill') {
            return true;
        }

        // If product model exists, check its product_type if not passed
        if ($pId && ! $productType) {
            $p = Product::find($pId);
            if ($p && ($p->product_type ?? null) === 'refill') {
                return true;
            }
        }

        // 3. Name or SKU contains 'refill' / 'refil' (even if product_type is not set)
        if (stripos($prodName, 'refill') !== false ||
            stripos($prodName, 'refil') !== false ||
            stripos($prodSku, 'REFILL') !== false) {
            return true;
        }

        // 4. Cane / 20L / Jar patterns
        return (stripos($prodName, '20L') !== false) ||
            (stripos($prodName, '20 L') !== false) ||
            (stripos($prodName, '20-Lit') !== false) ||
            (stripos($prodName, '20Lit') !== false) ||
            (stripos($prodName, '20 Litre') !== false) ||
            (stripos($prodName, 'Can') !== false) ||
            (stripos($prodName, 'Cane') !== false) ||
            (stripos($prodName, 'Jar') !== false) ||
            (stripos($prodUnit, 'Can') !== false) ||
            (stripos($prodUnit, 'Cane') !== false) ||
            (stripos($prodUnit, 'Jar') !== false) ||
            (stripos($prodSku, '20L') !== false) ||
            (stripos($prodSku, 'CAN') !== false);
    }

    /**
     * Generates complete monthly dealer ledger matrix data matching Excel layout.
     */
    public function generateMonthlyLedger(int $year, int $month, array $dealerIds = [], ?int $caneProductId = null): array
    {
        $startDate = Carbon::createFromDate($year, $month, 1, 'Asia/Kolkata')->startOfDay();
        $daysInMonth = $startDate->daysInMonth;
        $endDate = $startDate->copy()->endOfMonth()->startOfDay();
        $monthName = $startDate->format('F Y');

        // Resolve cane product
        $caneProduct = null;
        if ($caneProductId) {
            $caneProduct = Product::find($caneProductId);
        }
        if (! $caneProduct) {
            $caneProduct = Product::where('product_type', 'refill')
                ->orWhere('name', 'like', '%refill%')
                ->orWhere('name', 'like', '%20L%')
                ->orWhere('name', 'like', '%Can%')
                ->orWhere('name', 'like', '%Cane%')
                ->first() ?? Product::first();
        }
        $caneProductId = $caneProduct?->id;

        // Resolve dealers
        $dealerQuery = Dealer::query()->orderBy('name');
        if (! empty($dealerIds)) {
            $dealerQuery->whereIn('id', $dealerIds);
        } else {
            $dealerQuery->where('is_active', true);
        }
        $dealers = $dealerQuery->get();

        // Calculate Month Opening Balance for each dealer
        $monthOpeningBalances = [];
        foreach ($dealers as $dealer) {
            $initialOp = 0.0;
            if ($dealer->opening_balance != 0) {
                $opDate = $dealer->opening_date ? Carbon::parse($dealer->opening_date)->toDateString() : null;
                if (! $opDate || $opDate <= $startDate->toDateString()) {
                    $initialOp = (float) $dealer->opening_balance;
                }
            }

            $preSupplies = DealerSupply::with('items.product')
                ->where('dealer_id', $dealer->id)
                ->where('status', 'delivered')
                ->where('supply_date', '<', $startDate->toDateString())
                ->get();

            $preDebit = 0.0;
            foreach ($preSupplies as $ps) {
                foreach ($ps->items as $it) {
                    $p = $it->product;
                    $pName = $p?->name ?? $it->notes ?? '';
                    $pSku = $p?->sku ?? '';
                    $pUnit = $it->unit ?? $p?->unit ?? '';
                    $pType = $p?->product_type ?? null;
                    if ($this->isRefillCaneProduct($it->product_id, $pName, $pSku, $pUnit, $caneProductId, $pType)) {
                        $preDebit += (float) ($it->line_total ?? $it->total_amount ?? ($it->quantity * $it->unit_price));
                    }
                }
            }

            $preCredit = (float) DealerPayment::where('dealer_id', $dealer->id)
                ->where('status', 'completed')
                ->where('payment_date', '<', $startDate->toDateString())
                ->sum('amount');

            $monthOpeningBalances[$dealer->id] = round($initialOp + $preDebit - $preCredit, 2);
        }

        // Fetch all delivered supplies for the month
        $supplies = DealerSupply::with(['items.product'])
            ->whereIn('dealer_id', $dealers->pluck('id'))
            ->where('status', 'delivered')
            ->whereBetween('supply_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // Fetch all completed payments for the month
        $payments = DealerPayment::whereIn('dealer_id', $dealers->pluck('id'))
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // Fetch daily others transactions
        $othersEntries = DailyOtherTransaction::whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(fn ($o) => Carbon::parse($o->transaction_date)->toDateString());

        // Group supplies by date and dealer
        $dailySupplies = [];
        $productBreakdownTotals = [];

        foreach ($supplies as $supply) {
            $dateStr = Carbon::parse($supply->supply_date)->toDateString();
            $dId = $supply->dealer_id;

            if (! isset($dailySupplies[$dateStr])) {
                $dailySupplies[$dateStr] = [];
            }
            if (! isset($dailySupplies[$dateStr][$dId])) {
                $dailySupplies[$dateStr][$dId] = [
                    'cane_qty' => 0,
                    'filling_amount' => 0.0,
                    'items' => [],
                ];
            }

            foreach ($supply->items as $item) {
                $pId = $item->product_id;
                $qty = (int) $item->quantity;
                $prod = $item->product;
                $prodName = $prod?->name ?? $item->notes ?? 'Product #'.$pId;
                $prodSku = $prod?->sku ?? '';
                $prodUnit = $item->unit ?? $prod?->unit ?? 'Nos';
                $prodType = $prod?->product_type ?? null;

                // Check if this item is considered 20L / refill water cane (Dealer Ledger = With Refill only)
                $isCane = $this->isRefillCaneProduct($pId, $prodName, $prodSku, $prodUnit, $caneProductId, $prodType);

                if ($isCane) {
                    $itemAmount = (float) ($item->line_total ?? $item->total_amount ?? ($qty * $item->unit_price));
                    $dailySupplies[$dateStr][$dId]['cane_qty'] += $qty;
                    // Strict separation: Refill products are count-only in the Dealer Ledger; do not add to filling amount
                    $isPureRefill = ($prodType === 'refill') || stripos($prodName, 'refill') !== false || stripos($prodName, 'refil') !== false;
                    if (! $isPureRefill) {
                        $dailySupplies[$dateStr][$dId]['filling_amount'] += $itemAmount;
                    }

                    if (! isset($productBreakdownTotals[$pId])) {
                        $productBreakdownTotals[$pId] = [
                            'product_id' => $pId,
                            'name' => $prodName,
                            'sku' => $prodSku,
                            'unit' => $prodUnit,
                            'total_quantity' => 0,
                            'total_amount' => 0.0,
                        ];
                    }
                    $productBreakdownTotals[$pId]['total_quantity'] += $qty;
                    if (! $isPureRefill) {
                        $productBreakdownTotals[$pId]['total_amount'] += $itemAmount;
                    }
                }
            }
        }

        // Group payments by date and dealer
        $dailyPayments = [];
        foreach ($payments as $payment) {
            // Check if explicitly excluded from ledger amount section
            if (isset($payment->include_in_ledger_amount) && ! $payment->include_in_ledger_amount) {
                continue;
            }
            $dateStr = Carbon::parse($payment->payment_date)->toDateString();
            $dId = $payment->dealer_id;

            if (! isset($dailyPayments[$dateStr])) {
                $dailyPayments[$dateStr] = [];
            }
            if (! isset($dailyPayments[$dateStr][$dId])) {
                $dailyPayments[$dateStr][$dId] = 0.0;
            }
            $dailyPayments[$dateStr][$dId] += (float) $payment->amount;
        }

        // Build Matrix Calendar Dates
        $dates = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = Carbon::createFromDate($year, $month, $day, 'Asia/Kolkata');
            $dateKey = $currentDate->toDateString();
            $period = $day <= 15 ? 1 : 2;

            $dayRow = [
                'day' => $day,
                'date' => $dateKey,
                'day_name' => $currentDate->format('D'),
                'formatted' => $currentDate->format('d/m/Y'),
                'period' => $period,
                'dealers' => [],
                'others' => [
                    'cane_qty' => 0,
                    'filling_amount' => 0.0,
                    'payments_received' => 0.0,
                    'notes' => null,
                ],
                'totals' => [
                    'cane_qty' => 0,
                    'filling_amount' => 0.0,
                    'payments_received' => 0.0,
                ],
            ];

            // Others entry for this day
            if (isset($othersEntries[$dateKey])) {
                $otherRec = $othersEntries[$dateKey];
                $dayRow['others'] = [
                    'cane_qty' => (int) $otherRec->cane_quantity,
                    'filling_amount' => (float) $otherRec->filling_amount,
                    'payments_received' => (float) $otherRec->payments_received,
                    'notes' => $otherRec->notes,
                ];
                $dayRow['totals']['cane_qty'] += $dayRow['others']['cane_qty'];
                $dayRow['totals']['filling_amount'] += $dayRow['others']['filling_amount'];
                $dayRow['totals']['payments_received'] += $dayRow['others']['payments_received'];
            }

            // Dealer entries for this day
            foreach ($dealers as $dealer) {
                $dId = $dealer->id;
                $caneQty = $dailySupplies[$dateKey][$dId]['cane_qty'] ?? 0;
                $fillingAmt = $dailySupplies[$dateKey][$dId]['filling_amount'] ?? 0.0;
                $paymentAmt = $dailyPayments[$dateKey][$dId] ?? 0.0;

                $dayRow['dealers'][$dId] = [
                    'cane_qty' => $caneQty,
                    'filling_amount' => round($fillingAmt, 2),
                    'payments_received' => round($paymentAmt, 2),
                ];

                $dayRow['totals']['cane_qty'] += $caneQty;
                $dayRow['totals']['filling_amount'] += $fillingAmt;
                $dayRow['totals']['payments_received'] += $paymentAmt;
            }

            $dayRow['totals']['filling_amount'] = round($dayRow['totals']['filling_amount'], 2);
            $dayRow['totals']['payments_received'] = round($dayRow['totals']['payments_received'], 2);

            $dates[$day] = $dayRow;
        }

        // Compute Period 1 (Days 1..15)
        $p1Summary = [
            'period' => 1,
            'title' => 'Period 1 (Days 1 - 15)',
            'dealers' => [],
            'others' => [
                'opening_balance' => 0.0,
                'total_canes' => 0,
                'total_filling' => 0.0,
                'total_due' => 0.0,
                'total_payments' => 0.0,
                'closing_balance' => 0.0,
            ],
            'totals' => [
                'opening_balance' => 0.0,
                'total_canes' => 0,
                'total_filling' => 0.0,
                'total_due' => 0.0,
                'total_payments' => 0.0,
                'closing_balance' => 0.0,
            ],
        ];

        foreach ($dealers as $dealer) {
            $dId = $dealer->id;
            $opBal = $monthOpeningBalances[$dId] ?? 0.0;
            $totCanes = 0;
            $totFilling = 0.0;
            $totPayments = 0.0;

            for ($d = 1; $d <= 15; $d++) {
                if (isset($dates[$d])) {
                    $totCanes += $dates[$d]['dealers'][$dId]['cane_qty'] ?? 0;
                    $totFilling += $dates[$d]['dealers'][$dId]['filling_amount'] ?? 0.0;
                    $totPayments += $dates[$d]['dealers'][$dId]['payments_received'] ?? 0.0;
                }
            }

            $totDue = round($opBal + $totFilling, 2);
            $closingBal = round($totDue - $totPayments, 2);

            $p1Summary['dealers'][$dId] = [
                'opening_balance' => $opBal,
                'total_canes' => $totCanes,
                'total_filling' => round($totFilling, 2),
                'total_due' => $totDue,
                'total_payments' => round($totPayments, 2),
                'closing_balance' => $closingBal,
            ];

            $p1Summary['totals']['opening_balance'] += $opBal;
            $p1Summary['totals']['total_canes'] += $totCanes;
            $p1Summary['totals']['total_filling'] += $totFilling;
            $p1Summary['totals']['total_payments'] += $totPayments;
        }

        // P1 Others
        for ($d = 1; $d <= 15; $d++) {
            if (isset($dates[$d])) {
                $p1Summary['others']['total_canes'] += $dates[$d]['others']['cane_qty'];
                $p1Summary['others']['total_filling'] += $dates[$d]['others']['filling_amount'];
                $p1Summary['others']['total_payments'] += $dates[$d]['others']['payments_received'];
            }
        }
        $p1Summary['others']['total_due'] = round($p1Summary['others']['opening_balance'] + $p1Summary['others']['total_filling'], 2);
        $p1Summary['others']['closing_balance'] = round($p1Summary['others']['total_due'] - $p1Summary['others']['total_payments'], 2);

        $p1Summary['totals']['opening_balance'] = round($p1Summary['totals']['opening_balance'] + $p1Summary['others']['opening_balance'], 2);
        $p1Summary['totals']['total_canes'] += $p1Summary['others']['total_canes'];
        $p1Summary['totals']['total_filling'] = round($p1Summary['totals']['total_filling'] + $p1Summary['others']['total_filling'], 2);
        $p1Summary['totals']['total_due'] = round($p1Summary['totals']['opening_balance'] + $p1Summary['totals']['total_filling'], 2);
        $p1Summary['totals']['total_payments'] = round($p1Summary['totals']['total_payments'] + $p1Summary['others']['total_payments'], 2);
        $p1Summary['totals']['closing_balance'] = round($p1Summary['totals']['total_due'] - $p1Summary['totals']['total_payments'], 2);

        // Compute Period 2 (Days 16..$daysInMonth)
        // CRITICAL INVARIANT: Period-one closing balance becomes period-two opening balance!
        $p2Summary = [
            'period' => 2,
            'title' => 'Period 2 (Days 16 - '.$daysInMonth.')',
            'dealers' => [],
            'others' => [
                'opening_balance' => $p1Summary['others']['closing_balance'],
                'total_canes' => 0,
                'total_filling' => 0.0,
                'total_due' => 0.0,
                'total_payments' => 0.0,
                'closing_balance' => 0.0,
            ],
            'totals' => [
                'opening_balance' => 0.0,
                'total_canes' => 0,
                'total_filling' => 0.0,
                'total_due' => 0.0,
                'total_payments' => 0.0,
                'closing_balance' => 0.0,
            ],
        ];

        foreach ($dealers as $dealer) {
            $dId = $dealer->id;
            $p2OpBal = $p1Summary['dealers'][$dId]['closing_balance']; // Carried forward from P1
            $totCanes = 0;
            $totFilling = 0.0;
            $totPayments = 0.0;

            for ($d = 16; $d <= $daysInMonth; $d++) {
                if (isset($dates[$d])) {
                    $totCanes += $dates[$d]['dealers'][$dId]['cane_qty'] ?? 0;
                    $totFilling += $dates[$d]['dealers'][$dId]['filling_amount'] ?? 0.0;
                    $totPayments += $dates[$d]['dealers'][$dId]['payments_received'] ?? 0.0;
                }
            }

            $totDue = round($p2OpBal + $totFilling, 2);
            $closingBal = round($totDue - $totPayments, 2);

            $p2Summary['dealers'][$dId] = [
                'opening_balance' => $p2OpBal,
                'total_canes' => $totCanes,
                'total_filling' => round($totFilling, 2),
                'total_due' => $totDue,
                'total_payments' => round($totPayments, 2),
                'closing_balance' => $closingBal,
            ];

            $p2Summary['totals']['opening_balance'] += $p2OpBal;
            $p2Summary['totals']['total_canes'] += $totCanes;
            $p2Summary['totals']['total_filling'] += $totFilling;
            $p2Summary['totals']['total_payments'] += $totPayments;
        }

        // P2 Others
        for ($d = 16; $d <= $daysInMonth; $d++) {
            if (isset($dates[$d])) {
                $p2Summary['others']['total_canes'] += $dates[$d]['others']['cane_qty'];
                $p2Summary['others']['total_filling'] += $dates[$d]['others']['filling_amount'];
                $p2Summary['others']['total_payments'] += $dates[$d]['others']['payments_received'];
            }
        }
        $p2Summary['others']['total_due'] = round($p2Summary['others']['opening_balance'] + $p2Summary['others']['total_filling'], 2);
        $p2Summary['others']['closing_balance'] = round($p2Summary['others']['total_due'] - $p2Summary['others']['total_payments'], 2);

        $p2Summary['totals']['opening_balance'] = round($p2Summary['totals']['opening_balance'] + $p2Summary['others']['opening_balance'], 2);
        $p2Summary['totals']['total_canes'] += $p2Summary['others']['total_canes'];
        $p2Summary['totals']['total_filling'] = round($p2Summary['totals']['total_filling'] + $p2Summary['others']['total_filling'], 2);
        $p2Summary['totals']['total_due'] = round($p2Summary['totals']['opening_balance'] + $p2Summary['totals']['total_filling'], 2);
        $p2Summary['totals']['total_payments'] = round($p2Summary['totals']['total_payments'] + $p2Summary['others']['total_payments'], 2);
        $p2Summary['totals']['closing_balance'] = round($p2Summary['totals']['total_due'] - $p2Summary['totals']['total_payments'], 2);

        // Compute Month Grand Summary
        $monthSummary = [
            'month' => $month,
            'year' => $year,
            'dealers' => [],
            'others' => [
                'month_opening_balance' => $p1Summary['others']['opening_balance'],
                'total_canes' => $p1Summary['others']['total_canes'] + $p2Summary['others']['total_canes'],
                'total_filling' => round($p1Summary['others']['total_filling'] + $p2Summary['others']['total_filling'], 2),
                'total_payments' => round($p1Summary['others']['total_payments'] + $p2Summary['others']['total_payments'], 2),
                'month_closing_balance' => $p2Summary['others']['closing_balance'],
            ],
            'totals' => [
                'month_opening_balance' => $p1Summary['totals']['opening_balance'],
                'total_canes' => $p1Summary['totals']['total_canes'] + $p2Summary['totals']['total_canes'],
                'total_filling' => round($p1Summary['totals']['total_filling'] + $p2Summary['totals']['total_filling'], 2),
                'total_payments' => round($p1Summary['totals']['total_payments'] + $p2Summary['totals']['total_payments'], 2),
                'month_closing_balance' => $p2Summary['totals']['closing_balance'],
            ],
        ];

        foreach ($dealers as $dealer) {
            $dId = $dealer->id;
            $monthSummary['dealers'][$dId] = [
                'month_opening_balance' => $p1Summary['dealers'][$dId]['opening_balance'],
                'total_canes' => $p1Summary['dealers'][$dId]['total_canes'] + $p2Summary['dealers'][$dId]['total_canes'],
                'total_filling' => round($p1Summary['dealers'][$dId]['total_filling'] + $p2Summary['dealers'][$dId]['total_filling'], 2),
                'total_payments' => round($p1Summary['dealers'][$dId]['total_payments'] + $p2Summary['dealers'][$dId]['total_payments'], 2),
                'month_closing_balance' => $p2Summary['dealers'][$dId]['closing_balance'],
            ];
        }

        $settings = CompanySetting::getSettings();

        return [
            'company' => [
                'name' => $settings->company_name ?? 'Marina Aqua Private Limited',
                'tagline' => $settings->tagline ?? 'Pure Living Water',
                'address' => $settings->address ?? 'No. 42, Water Works Road, Guindy, Chennai - 600032, Tamil Nadu',
                'gstin' => $settings->gstin ?? '33AABCM9876C1Z8',
                'phone' => $settings->phone ?? '+91 44 2233 4455',
                'email' => $settings->email,
                'logo_url' => $settings->logo_url,
            ],
            'report_meta' => [
                'title' => 'MONTHLY DEALER LEDGER REPORT',
                'month' => $month,
                'year' => $year,
                'month_name' => $monthName,
                'days_in_month' => $daysInMonth,
                'p1_days' => 15,
                'p2_days' => $daysInMonth - 15,
                'cane_product' => [
                    'id' => $caneProduct?->id,
                    'name' => $caneProduct?->name ?? '20L Water Can',
                    'unit' => $caneProduct?->unit?->code ?? 'CAN',
                ],
            ],
            'dealers' => $dealers->map(fn ($d) => [
                'id' => $d->id,
                'code' => $d->code,
                'name' => $d->name,
                'phone' => $d->phone,
            ])->values(),
            'dates' => array_values($dates),
            'period1_summary' => $p1Summary,
            'period2_summary' => $p2Summary,
            'month_summary' => $monthSummary,
            'product_breakdown' => array_values($productBreakdownTotals),
        ];
    }

    /**
     * Upserts a daily "Others" transaction.
     */
    public function upsertOtherTransaction(array $data, ?int $userId = null): DailyOtherTransaction
    {
        $date = Carbon::parse($data['transaction_date'])->toDateString();
        $day = Carbon::parse($date)->day;
        $period = $day <= 15 ? 1 : 2;

        return DailyOtherTransaction::updateOrCreate(
            ['transaction_date' => $date],
            [
                'period' => $period,
                'cane_quantity' => (int) ($data['cane_quantity'] ?? 0),
                'filling_amount' => round((float) ($data['filling_amount'] ?? 0.0), 2),
                'payments_received' => round((float) ($data['payments_received'] ?? 0.0), 2),
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    /**
     * Exports the monthly dealer ledger as a styled A4 landscape PDF.
     */
    public function exportPdf(int $year, int $month, array $dealerIds = [], ?int $caneProductId = null)
    {
        $data = $this->generateMonthlyLedger($year, $month, $dealerIds, $caneProductId);

        $pdf = Pdf::loadView('reports.dealer_ledger_pdf', $data);
        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'Helvetica',
            'margin_top' => 8,
            'margin_right' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
        ]);

        $fileName = sprintf('Dealer_Ledger_Report_%d_%02d.pdf', $year, $month);

        return $pdf->download($fileName);
    }

    /**
     * Exports the monthly dealer ledger as a styled Excel (.xlsx) file.
     */
    public function exportExcel(int $year, int $month, array $dealerIds = [], ?int $caneProductId = null): StreamedResponse
    {
        $data = $this->generateMonthlyLedger($year, $month, $dealerIds, $caneProductId);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Dealer Ledger '.$data['report_meta']['month_name']);

        // Set page orientation to landscape
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        $dealers = $data['dealers'];
        $dealerCount = count($dealers);
        // Columns: Col A = Date, Col B = Day, Col C.. = Dealers, Col (N-1) = Others, Col N = Total
        $totalColIndex = 3 + $dealerCount + 1; // 1-based index
        $lastColLetter = Coordinate::stringFromColumnIndex($totalColIndex);

        // Header Section
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->setCellValue('A1', $data['company']['name']);
        $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true)->setColor(new Color('001E3D'));
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->setCellValue('A2', 'MONTHLY DEALER LEDGER REPORT - '.strtoupper($data['report_meta']['month_name']));
        $sheet->getStyle('A2')->getFont()->setSize(12)->setBold(true)->setColor(new Color('007791'));
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("A3:{$lastColLetter}3");
        $sheet->setCellValue('A3', 'Cane Metric: '.$data['report_meta']['cane_product']['name'].' ('.$data['report_meta']['cane_product']['unit'].') | All amounts in INR (₹)');
        $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Row 5: Column Headers
        $sheet->setCellValue('A5', 'Date');
        $sheet->setCellValue('B5', 'Day');
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(6);

        $col = 3;
        foreach ($dealers as $d) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue("{$colLetter}5", $d['name']."\n(".$d['code'].')');
            $sheet->getColumnDimension($colLetter)->setWidth(14);
            $col++;
        }
        // Others
        $othersColLetter = Coordinate::stringFromColumnIndex($col);
        $sheet->setCellValue("{$othersColLetter}5", "Others\n(Retail)");
        $sheet->getColumnDimension($othersColLetter)->setWidth(12);
        $col++;
        // Total
        $totalColLetter = Coordinate::stringFromColumnIndex($col);
        $sheet->setCellValue("{$totalColLetter}5", "TOTAL\nSUMMARY");
        $sheet->getColumnDimension($totalColLetter)->setWidth(14);

        $headerRange = "A5:{$lastColLetter}5";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new Color('FFFFFF'));
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('003366');
        $sheet->getStyle($headerRange)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(5)->setRowHeight(32);

        $row = 6;

        // Function helper to write period blocks
        $writePeriod = function ($periodNum, $startDay, $endDay, $summaryKey) use (&$row, $sheet, $dealers, $data, $lastColLetter, $othersColLetter, $totalColLetter) {
            $periodSummary = $data[$summaryKey];

            // Period Title Banner
            $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
            $sheet->setCellValue("A{$row}", '--- '.strtoupper($periodSummary['title']).' ---');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(10)->setColor(new Color('003366'));
            $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E6F0FA');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;

            // Opening Balance Row
            $sheet->setCellValue("A{$row}", 'OPENING');
            $sheet->setCellValue("B{$row}", 'BAL');
            $col = 3;
            foreach ($dealers as $d) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $op = $periodSummary['dealers'][$d['id']]['opening_balance'];
                $sheet->setCellValue("{$colLetter}{$row}", $op);
                $sheet->getStyle("{$colLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $col++;
            }
            $sheet->setCellValue("{$othersColLetter}{$row}", $periodSummary['others']['opening_balance']);
            $sheet->getStyle("{$othersColLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->setCellValue("{$totalColLetter}{$row}", $periodSummary['totals']['opening_balance']);
            $sheet->getStyle("{$totalColLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

            $opRange = "A{$row}:{$lastColLetter}{$row}";
            $sheet->getStyle($opRange)->getFont()->setBold(true)->setColor(new Color('8A5000'));
            $sheet->getStyle($opRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3E0');
            $row++;

            // Daily Rows
            for ($d = $startDay; $d <= $endDay; $d++) {
                $dayData = $data['dates'][$d - 1] ?? null;
                if (! $dayData) {
                    continue;
                }

                $sheet->setCellValue("A{$row}", $dayData['formatted']);
                $sheet->setCellValue("B{$row}", $dayData['day_name']);

                $col = 3;
                foreach ($dealers as $dealer) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $dEntry = $dayData['dealers'][$dealer['id']] ?? ['cane_qty' => 0, 'filling_amount' => 0, 'payments_received' => 0];

                    $text = $dEntry['cane_qty'];
                    if ($dEntry['payments_received'] > 0) {
                        $text .= ' (₹'.round($dEntry['payments_received']).' pd)';
                    } elseif ($dEntry['filling_amount'] > 0) {
                        $text .= ' [₹'.round($dEntry['filling_amount']).']';
                    }

                    $sheet->setCellValue("{$colLetter}{$row}", $text);
                    $col++;
                }

                // Others
                $oEntry = $dayData['others'];
                $oText = $oEntry['cane_qty'];
                if ($oEntry['payments_received'] > 0) {
                    $oText .= ' (₹'.round($oEntry['payments_received']).' pd)';
                }
                $sheet->setCellValue("{$othersColLetter}{$row}", $oText);

                // Total
                $tEntry = $dayData['totals'];
                $sheet->setCellValue("{$totalColLetter}{$row}", "{$tEntry['cane_qty']} cans\n₹".number_format($tEntry['filling_amount'], 2));
                $sheet->getStyle("{$totalColLetter}{$row}")->getAlignment()->setWrapText(true);

                if ($d % 2 == 0) {
                    $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F9FBFC');
                }
                $row++;
            }

            // Period Summary Rows (Clearly separated and color-coded)
            // 1. Total Canes
            $sheet->setCellValue("A{$row}", 'TOTAL');
            $sheet->setCellValue("B{$row}", 'CANES');
            $col = 3;
            foreach ($dealers as $d) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue("{$colLetter}{$row}", $periodSummary['dealers'][$d['id']]['total_canes']);
                $col++;
            }
            $sheet->setCellValue("{$othersColLetter}{$row}", $periodSummary['others']['total_canes']);
            $sheet->setCellValue("{$totalColLetter}{$row}", $periodSummary['totals']['total_canes']);
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E1F5FE');
            $row++;

            // 2. Filling Amount
            $sheet->setCellValue("A{$row}", 'FILLING');
            $sheet->setCellValue("B{$row}", 'AMOUNT');
            $col = 3;
            foreach ($dealers as $d) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue("{$colLetter}{$row}", $periodSummary['dealers'][$d['id']]['total_filling']);
                $sheet->getStyle("{$colLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $col++;
            }
            $sheet->setCellValue("{$othersColLetter}{$row}", $periodSummary['others']['total_filling']);
            $sheet->getStyle("{$othersColLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->setCellValue("{$totalColLetter}{$row}", $periodSummary['totals']['total_filling']);
            $sheet->getStyle("{$totalColLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFont()->setBold(true)->setColor(new Color('0D47A1'));
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E3F2FD');
            $row++;

            // 3. Total Due (Opening + Filling)
            $sheet->setCellValue("A{$row}", 'TOTAL');
            $sheet->setCellValue("B{$row}", 'DUE');
            $col = 3;
            foreach ($dealers as $d) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue("{$colLetter}{$row}", $periodSummary['dealers'][$d['id']]['total_due']);
                $sheet->getStyle("{$colLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $col++;
            }
            $sheet->setCellValue("{$othersColLetter}{$row}", $periodSummary['others']['total_due']);
            $sheet->getStyle("{$othersColLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->setCellValue("{$totalColLetter}{$row}", $periodSummary['totals']['total_due']);
            $sheet->getStyle("{$totalColLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFont()->setBold(true)->setColor(new Color('E65100'));
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0B2');
            $row++;

            // 4. Payments Received
            $sheet->setCellValue("A{$row}", 'PAYMENTS');
            $sheet->setCellValue("B{$row}", 'PAID');
            $col = 3;
            foreach ($dealers as $d) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue("{$colLetter}{$row}", $periodSummary['dealers'][$d['id']]['total_payments']);
                $sheet->getStyle("{$colLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $col++;
            }
            $sheet->setCellValue("{$othersColLetter}{$row}", $periodSummary['others']['total_payments']);
            $sheet->getStyle("{$othersColLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->setCellValue("{$totalColLetter}{$row}", $periodSummary['totals']['total_payments']);
            $sheet->getStyle("{$totalColLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFont()->setBold(true)->setColor(new Color('1B5E20'));
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E8F5E9');
            $row++;

            // 5. Closing Balance
            $sheet->setCellValue("A{$row}", 'CLOSING');
            $sheet->setCellValue("B{$row}", 'BALANCE');
            $col = 3;
            foreach ($dealers as $d) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue("{$colLetter}{$row}", $periodSummary['dealers'][$d['id']]['closing_balance']);
                $sheet->getStyle("{$colLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $col++;
            }
            $sheet->setCellValue("{$othersColLetter}{$row}", $periodSummary['others']['closing_balance']);
            $sheet->getStyle("{$othersColLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->setCellValue("{$totalColLetter}{$row}", $periodSummary['totals']['closing_balance']);
            $sheet->getStyle("{$totalColLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFont()->setBold(true)->setColor(new Color('B71C1C'));
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEBEE');
            $row += 2; // Spacing
        };

        // Write Period 1 (1..15)
        $writePeriod(1, 1, 15, 'period1_summary');

        // Write Period 2 (16..daysInMonth)
        $writePeriod(2, 16, $data['report_meta']['days_in_month'], 'period2_summary');

        // Month Grand Summary Header
        $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
        $sheet->setCellValue("A{$row}", '=== MONTH GRAND TOTALS ('.strtoupper($data['report_meta']['month_name']).') ===');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11)->setColor(new Color('FFFFFF'));
        $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('001E3D');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        // Month Summary Rows
        $mSummary = $data['month_summary'];
        $summaryMetrics = [
            ['Month Opening Bal', 'month_opening_balance', 'FFF3E0', '8A5000', true],
            ['Total Canes Delivered', 'total_canes', 'E1F5FE', '01579B', false],
            ['Total Filling Amount', 'total_filling', 'E3F2FD', '0D47A1', true],
            ['Total Payments Received', 'total_payments', 'E8F5E9', '1B5E20', true],
            ['Month Closing Balance', 'month_closing_balance', 'FFEBEE', 'B71C1C', true],
        ];

        foreach ($summaryMetrics as [$label, $key, $bgColor, $textColor, $isCurrency]) {
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", $label);
            $col = 3;
            foreach ($dealers as $d) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $val = $mSummary['dealers'][$d['id']][$key];
                $sheet->setCellValue("{$colLetter}{$row}", $val);
                if ($isCurrency) {
                    $sheet->getStyle("{$colLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
                $col++;
            }
            $oVal = $mSummary['others'][$key];
            $sheet->setCellValue("{$othersColLetter}{$row}", $oVal);
            if ($isCurrency) {
                $sheet->getStyle("{$othersColLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $tVal = $mSummary['totals'][$key];
            $sheet->setCellValue("{$totalColLetter}{$row}", $tVal);
            if ($isCurrency) {
                $sheet->getStyle("{$totalColLetter}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $range = "A{$row}:{$lastColLetter}{$row}";
            $sheet->getStyle($range)->getFont()->setBold(true)->setColor(new Color($textColor));
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bgColor);
            $row++;
        }

        // Apply grid borders
        $tableRange = "A5:{$lastColLetter}".($row - 1);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('D0D7DE'));

        // Output stream
        $fileName = sprintf('Dealer_Ledger_Report_%d_%02d.xlsx', $year, $month);
        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(
            function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    /**
     * Exports the monthly dealer ledger as CSV.
     */
    public function exportCsv(int $year, int $month, array $dealerIds = [], ?int $caneProductId = null): StreamedResponse
    {
        $data = $this->generateMonthlyLedger($year, $month, $dealerIds, $caneProductId);
        $fileName = sprintf('Dealer_Ledger_Report_%d_%02d.csv', $year, $month);

        return new StreamedResponse(
            function () use ($data) {
                $handle = fopen('php://output', 'w');

                // Header
                fputcsv($handle, [$data['company']['name']]);
                fputcsv($handle, ['MONTHLY DEALER LEDGER REPORT - '.$data['report_meta']['month_name']]);
                fputcsv($handle, []);

                // Column Headers
                $headers = ['Date', 'Day'];
                foreach ($data['dealers'] as $d) {
                    $headers[] = $d['name'].' ('.$d['code'].')';
                }
                $headers[] = 'Others';
                $headers[] = 'TOTAL';
                fputcsv($handle, $headers);

                // Helper for CSV periods
                $outputCsvPeriod = function ($periodKey, $startDay, $endDay) use ($handle, $data) {
                    $p = $data[$periodKey];
                    fputcsv($handle, ['--- '.$p['title'].' ---']);

                    // Opening
                    $opRow = ['OPENING', 'BAL'];
                    foreach ($data['dealers'] as $d) {
                        $opRow[] = $p['dealers'][$d['id']]['opening_balance'];
                    }
                    $opRow[] = $p['others']['opening_balance'];
                    $opRow[] = $p['totals']['opening_balance'];
                    fputcsv($handle, $opRow);

                    // Daily
                    for ($d = $startDay; $d <= $endDay; $d++) {
                        $dayData = $data['dates'][$d - 1] ?? null;
                        if (! $dayData) {
                            continue;
                        }
                        $row = [$dayData['formatted'], $dayData['day_name']];
                        foreach ($data['dealers'] as $dealer) {
                            $row[] = $dayData['dealers'][$dealer['id']]['cane_qty'];
                        }
                        $row[] = $dayData['others']['cane_qty'];
                        $row[] = $dayData['totals']['cane_qty'];
                        fputcsv($handle, $row);
                    }

                    // Summary
                    $metrics = [
                        ['TOTAL CANES', 'total_canes'],
                        ['FILLING AMOUNT', 'total_filling'],
                        ['TOTAL DUE', 'total_due'],
                        ['PAYMENTS PAID', 'total_payments'],
                        ['CLOSING BALANCE', 'closing_balance'],
                    ];
                    foreach ($metrics as [$label, $key]) {
                        $r = [$label, ''];
                        foreach ($data['dealers'] as $d) {
                            $r[] = $p['dealers'][$d['id']][$key];
                        }
                        $r[] = $p['others'][$key];
                        $r[] = $p['totals'][$key];
                        fputcsv($handle, $r);
                    }
                    fputcsv($handle, []);
                };

                $outputCsvPeriod('period1_summary', 1, 15);
                $outputCsvPeriod('period2_summary', 16, $data['report_meta']['days_in_month']);

                fclose($handle);
            },
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]
        );
    }

    /**
     * Retrieve all original source transactions contributing to a specific dealer cell on a given date.
     */
    public function getCellTransactions(int $dealerId, string $date): array
    {
        $dateStr = Carbon::parse($date, 'Asia/Kolkata')->toDateString();
        $dealer = Dealer::find($dealerId);

        $supplies = DealerSupply::with(['items.product', 'payments'])
            ->where('dealer_id', $dealerId)
            ->whereDate('supply_date', $dateStr)
            ->orderBy('id', 'desc')
            ->get();

        $payments = DealerPayment::with(['dealer', 'supply'])
            ->where('dealer_id', $dealerId)
            ->whereDate('payment_date', $dateStr)
            ->orderBy('id', 'desc')
            ->get();

        $ledgerEntries = \App\Models\DealerLedgerEntry::where('dealer_id', $dealerId)
            ->whereDate('entry_date', $dateStr)
            ->orderBy('id', 'desc')
            ->get();

        $transactions = [];

        // Add supplies
        foreach ($supplies as $supply) {
            $items = [];
            $totalCaneCount = 0;
            foreach ($supply->items as $it) {
                $p = $it->product;
                $pType = $p?->product_type ?? 'normal';
                $isRefill = $pType === 'refill' || stripos($p?->name ?? '', 'refill') !== false;
                if ($isRefill) {
                    $totalCaneCount += (int) $it->quantity;
                }
                $items[] = [
                    'id' => $it->id,
                    'product_id' => $it->product_id,
                    'product_name' => $p?->name ?? 'Product #'.$it->product_id,
                    'product_type' => $pType,
                    'is_refill' => $isRefill,
                    'quantity' => (float) $it->quantity,
                    'unit' => $it->unit ?? 'Nos',
                    'unit_price' => (float) $it->unit_price,
                    'line_total' => (float) $it->line_total,
                ];
            }

            $transactions[] = [
                'id' => 'supply-'.$supply->id,
                'source_table' => 'dealer_supplies',
                'source_id' => $supply->id,
                'transaction_no' => $supply->supply_no,
                'transaction_type' => 'Dealer Supply',
                'date' => $supply->supply_date->toDateString(),
                'dealer_id' => $dealerId,
                'dealer_name' => $dealer?->name ?? 'Dealer #'.$dealerId,
                'dealer_code' => $dealer?->code ?? '',
                'amount' => (float) $supply->total_amount,
                'count' => $totalCaneCount,
                'payment_status' => $supply->payment_status,
                'status' => $supply->status,
                'notes' => $supply->notes,
                'items' => $items,
                'can_edit' => true,
                'can_delete' => true,
                'created_at' => $supply->created_at?->toIso8601String(),
            ];
        }

        // Add payments
        foreach ($payments as $payment) {
            $transactions[] = [
                'id' => 'payment-'.$payment->id,
                'source_table' => 'dealer_payments',
                'source_id' => $payment->id,
                'transaction_no' => $payment->payment_no,
                'transaction_type' => 'Payment Received',
                'date' => $payment->payment_date ? $payment->payment_date->toDateString() : $dateStr,
                'dealer_id' => $dealerId,
                'dealer_name' => $dealer?->name ?? 'Dealer #'.$dealerId,
                'dealer_code' => $dealer?->code ?? '',
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method ?? 'cash',
                'payment_mode' => $payment->payment_mode ?? $payment->payment_method ?? 'cash',
                'reference_no' => $payment->reference_no,
                'status' => $payment->status,
                'remarks' => $payment->remarks,
                'notes' => $payment->notes,
                'linked_supply_id' => $payment->dealer_supply_id,
                'include_in_ledger_amount' => $payment->include_in_ledger_amount ?? true,
                'can_edit' => true,
                'can_delete' => true,
                'created_at' => $payment->created_at?->toIso8601String(),
            ];
        }

        // If no supplies or payments, include ledger entries
        if (empty($transactions)) {
            foreach ($ledgerEntries as $le) {
                $transactions[] = [
                    'id' => 'ledger-'.$le->id,
                    'source_table' => 'dealer_ledger_entries',
                    'source_id' => $le->id,
                    'transaction_no' => $le->reference_no ?? 'LEDGER-'.$le->id,
                    'transaction_type' => ucfirst(str_replace('_', ' ', $le->entry_type ?? 'ledger')),
                    'date' => $le->entry_date ? $le->entry_date->toDateString() : $dateStr,
                    'dealer_id' => $dealerId,
                    'dealer_name' => $dealer?->name ?? '',
                    'amount' => (float) ($le->credit > 0 ? $le->credit : $le->debit),
                    'reference_no' => $le->reference_no,
                    'notes' => $le->narration,
                    'can_edit' => false,
                    'can_delete' => false,
                    'created_at' => $le->created_at?->toIso8601String(),
                ];
            }
        }

        return [
            'dealer' => $dealer ? [
                'id' => $dealer->id,
                'name' => $dealer->name,
                'code' => $dealer->code,
                'phone' => $dealer->phone,
            ] : null,
            'date' => $dateStr,
            'transactions' => $transactions,
        ];
    }
}
