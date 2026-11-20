<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\DealerRequest;
use App\Http\Resources\DealerResource;
use App\Models\Dealer;
use App\Models\DealerLedgerEntry;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealerController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $query = Dealer::withCount(['supplies', 'payments', 'productPrices']);

        // 1. Text Search
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // 2. Active Status Filter
        if ($request->has('is_active') && $request->query('is_active') !== '') {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('name', 'asc');

        if ($request->boolean('all')) {
            $dealers = $query->get();

            return $this->successResponse(DealerResource::collection($dealers), 'Dealers retrieved successfully.');
        }

        $perPage = (int) $request->query('per_page', 15);
        $dealers = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Dealers retrieved successfully.',
            'data' => DealerResource::collection($dealers->items()),
            'meta' => [
                'current_page' => $dealers->currentPage(),
                'per_page' => $dealers->perPage(),
                'total' => $dealers->total(),
                'last_page' => $dealers->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    public function store(DealerRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['opening_date'])) {
            $data['opening_date'] = now('Asia/Kolkata')->toDateString();
        }

        $dealer = Dealer::create($data);

        // Auto-create initial opening balance ledger entry
        $dealer->syncOpeningBalanceLedger(auth()->id());

        return $this->successResponse(
            new DealerResource($dealer->loadCount(['supplies', 'payments', 'productPrices'])),
            'Dealer registered successfully with opening balance ledger entry.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $dealer = Dealer::withCount(['supplies', 'payments', 'productPrices'])->find($id);

        if (! $dealer) {
            return $this->errorResponse('Dealer not found.', 404);
        }

        return $this->successResponse(new DealerResource($dealer), 'Dealer details retrieved successfully.');
    }

    public function update(DealerRequest $request, int $id): JsonResponse
    {
        $dealer = Dealer::find($id);

        if (! $dealer) {
            return $this->errorResponse('Dealer not found.', 404);
        }

        $data = $request->validated();
        $dealer->update($data);

        // Re-sync opening balance ledger entry
        $dealer->syncOpeningBalanceLedger(auth()->id());

        return $this->successResponse(
            new DealerResource($dealer->loadCount(['supplies', 'payments', 'productPrices'])),
            'Dealer updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $dealer = Dealer::find($id);

        if (! $dealer) {
            return $this->errorResponse('Dealer not found.', 404);
        }

        if ($dealer->isReferencedInTransactions()) {
            $dealer->update(['is_active' => false]);

            return $this->successResponse(
                new DealerResource($dealer->loadCount(['supplies', 'payments', 'productPrices'])),
                'Dealer has recorded supply invoices or payment collections. Deactivated instead of deleted to protect historical records.'
            );
        }

        $dealer->delete();

        return $this->successResponse(null, 'Dealer deleted successfully.');
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $dealer = Dealer::find($id);

        if (! $dealer) {
            return $this->errorResponse('Dealer not found.', 404);
        }

        $dealer->update(['is_active' => ! $dealer->is_active]);

        $statusLabel = $dealer->is_active ? 'activated' : 'deactivated';

        return $this->successResponse(
            new DealerResource($dealer->loadCount(['supplies', 'payments', 'productPrices'])),
            "Dealer {$statusLabel} successfully."
        );
    }

    public function ledger(int $id, Request $request): JsonResponse
    {
        $dealer = Dealer::find($id);

        if (! $dealer) {
            return $this->errorResponse('Dealer not found.', 404);
        }

        $query = DealerLedgerEntry::where('dealer_id', $id);

        if ($request->filled('start_date')) {
            $query->where('entry_date', '>=', $request->query('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->where('entry_date', '<=', $request->query('end_date'));
        }

        $entries = $query->orderBy('entry_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse([
            'dealer' => new DealerResource($dealer),
            'opening_balance' => (float) $dealer->opening_balance,
            'current_outstanding' => $dealer->calculateOutstandingBalance(),
            'entries' => $entries,
        ], 'Dealer ledger retrieved successfully.');
    }

    /**
     * Complete Dealer Account Statement with summary metrics and running balances.
     */
    public function statement(int $id, Request $request): JsonResponse
    {
        $dealer = Dealer::find($id);

        if (! $dealer) {
            return $this->errorResponse('Dealer not found.', 404);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $selYear = (int) $request->query('year');
            $selMonth = (int) $request->query('month');
            $startDate = \Carbon\Carbon::createFromDate($selYear, $selMonth, 1, 'Asia/Kolkata')->startOfMonth()->toDateString();
            $endDate = \Carbon\Carbon::createFromDate($selYear, $selMonth, 1, 'Asia/Kolkata')->endOfMonth()->toDateString();
        } else {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
            if ($startDate) {
                $cStart = \Carbon\Carbon::parse($startDate);
                $selYear = (int) $cStart->year;
                $selMonth = (int) $cStart->month;
            } else {
                $now = \Carbon\Carbon::now('Asia/Kolkata');
                $selYear = (int) $now->year;
                $selMonth = (int) $now->month;
                $startDate = $now->copy()->startOfMonth()->toDateString();
                $endDate = $now->copy()->endOfMonth()->toDateString();
            }
        }

        // Base opening balance of dealer
        $baseOpening = (float) $dealer->opening_balance;

        // Calculate opening balance at start of range (excluding invoice module entries)
        $effectiveOpeningBalance = $baseOpening;
        if ($startDate) {
            $priorDiff = DealerLedgerEntry::where('dealer_id', $id)
                ->where('entry_date', '<', $startDate)
                ->where('entry_type', '!=', 'opening_balance')
                ->where('narration', 'not like', '%Invoice%')
                ->where('narration', 'not like', '%Inv %')
                ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance_diff')
                ->value('balance_diff') ?? 0;
            $effectiveOpeningBalance += (float) $priorDiff;
        }

        $query = DealerLedgerEntry::where('dealer_id', $id)
            ->where('narration', 'not like', '%Invoice%')
            ->where('narration', 'not like', '%Inv %');
        if ($startDate) {
            $query->where('entry_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('entry_date', '<=', $endDate);
        }

        $entries = $query->orderBy('entry_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $running = $effectiveOpeningBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $totalSupplyAmount = 0.0;
        $totalPaymentsReceived = 0.0;

        $formattedEntries = [];
        foreach ($entries as $entry) {
            if ($entry->entry_type === 'opening_balance') {
                continue;
            }

            $debit = (float) $entry->debit;
            $credit = (float) $entry->credit;
            $running = round($running + $debit - $credit, 2);

            $totalDebit += $debit;
            $totalCredit += $credit;

            if ($entry->entry_type === 'supply' || $debit > 0) {
                $totalSupplyAmount += $debit;
            }
            if ($entry->entry_type === 'payment' || $credit > 0) {
                $totalPaymentsReceived += $credit;
            }

            $formattedEntries[] = [
                'id' => $entry->id,
                'entry_date' => $entry->entry_date ? $entry->entry_date->toDateString() : null,
                'reference_no' => $entry->reference_no ?? 'TRX-'.$entry->id,
                'entry_type' => $entry->entry_type,
                'type_label' => match($entry->entry_type) {
                    'supply' => 'Supply Invoice',
                    'payment' => 'Payment Received',
                    'adjustment' => 'Adjustment',
                    default => ucfirst(str_replace('_', ' ', $entry->entry_type ?? 'transaction')),
                },
                'reference_id' => $entry->reference_id,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running,
                'narration' => $entry->narration,
            ];
        }

        $closingBalance = $running;
        $debitBalance = $closingBalance > 0 ? $closingBalance : 0.0;
        $creditBalance = $closingBalance < 0 ? abs($closingBalance) : 0.0;

        // Breakdown: Refill, Bottle, Others, P1, and P2
        $suppliesQuery = \App\Models\DealerSupply::with('items.product')
            ->where('dealer_id', $id)
            ->where('status', 'delivered');
        if ($startDate) {
            $suppliesQuery->where('supply_date', '>=', $startDate);
        }
        if ($endDate) {
            $suppliesQuery->where('supply_date', '<=', $endDate);
        }
        $supplies = $suppliesQuery->get();

        $reportService = app(\App\Services\DealerLedgerReportService::class);

        $refillCount = 0;
        $refillAmount = 0.0;
        $bottleCount = 0;
        $bottleAmount = 0.0;
        $p1RefillAmount = 0.0;
        $p2RefillAmount = 0.0;

        foreach ($supplies as $sup) {
            $sDate = \Carbon\Carbon::parse($sup->supply_date);
            $day = (int) $sDate->day;
            foreach ($sup->items as $item) {
                $p = $item->product;
                $pName = $p?->name ?? $item->notes ?? '';
                $pSku = $p?->sku ?? '';
                $pUnit = $item->unit ?? $p?->unit ?? '';
                $pType = $p?->product_type;
                $pId = $p?->id ?? $item->product_id;
                $qty = (float) $item->quantity;
                $lineTotal = (float) ($item->line_total ?? $item->total_amount ?? ($qty * ($item->unit_price ?? 0)));

                $isRefill = $reportService->isRefillCaneProduct($pId, $pName, $pSku, $pUnit, null, $pType);
                if ($isRefill) {
                    $refillCount += (int) $qty;
                    $refillAmount += $lineTotal;
                    if ($day <= 15) {
                        $p1RefillAmount += $lineTotal;
                    } else {
                        $p2RefillAmount += $lineTotal;
                    }
                } else {
                    $bottleCount += (int) $qty;
                    $bottleAmount += $lineTotal;
                }
            }
        }

        // Payments breakdown (P1 and P2 include filling/refill payments only, strictly excluding bottle amounts)
        $paymentsQuery = \App\Models\DealerPayment::with(['supply.items.product', 'details'])
            ->where('dealer_id', $id)
            ->where('status', '!=', 'cancelled');
        if ($startDate) {
            $paymentsQuery->where('payment_date', '>=', $startDate);
        }
        if ($endDate) {
            $paymentsQuery->where('payment_date', '<=', $endDate);
        }
        $dealerPayments = $paymentsQuery->get();

        $othersPayments = 0.0;
        $p1Payments = 0.0;
        $p2Payments = 0.0;
        foreach ($dealerPayments as $pay) {
            $amt = (float) $pay->amount;
            $payDay = (int) \Carbon\Carbon::parse($pay->payment_date)->day;
            $fillingAmt = $reportService->getFillingPaymentAmount($pay);

            if (isset($pay->include_in_ledger_amount) && ! $pay->include_in_ledger_amount) {
                $othersPayments += $amt;
            }

            if ($payDay <= 15) {
                $p1Payments += $fillingAmt;
            } else {
                $p2Payments += $fillingAmt;
            }
        }

        // --- Monthly Statement Ledger Matrix (Date, Filling, Products, Cash, UPI) ---
        // 1. Resolve eligible product catalog columns (excluding invoice-only, excluding refill)
        $companyId = $dealer->company_id;
        $catalogProducts = \App\Models\Product::where('company_id', $companyId)
            ->where(function ($q) {
                $q->whereNull('for_invoice')->orWhere('for_invoice', false);
            })
            ->where(function ($q) {
                $q->whereNull('product_type')->orWhereRaw('LOWER(TRIM(product_type)) != ?', ['refill']);
            })
            ->where('is_active', true)
            ->orderBy('id', 'asc')
            ->get();

        $productsMap = [];
        foreach ($catalogProducts as $cp) {
            if (strtolower(trim((string) $cp->product_type)) === 'refill') {
                continue;
            }
            $productsMap[$cp->id] = [
                'id' => $cp->id,
                'name' => $cp->name,
                'sku' => $cp->sku,
                'product_type' => $cp->product_type ?? 'normal',
            ];
        }

        // 2. Accumulate daily transactions
        $dailyData = [];

        foreach ($supplies as $sup) {
            $dateKey = \Carbon\Carbon::parse($sup->supply_date)->toDateString();
            if (! isset($dailyData[$dateKey])) {
                $dailyData[$dateKey] = [
                    'date_raw' => $dateKey,
                    'filling' => 0,
                    'products' => [],
                    'cash' => 0.0,
                    'upi' => 0.0,
                ];
            }

            foreach ($sup->items as $item) {
                $p = $item->product;
                $pId = $p?->id ?? $item->product_id;
                $pName = $p?->name ?? $item->notes ?? '';
                $pSku = $p?->sku ?? '';
                $pUnit = $item->unit ?? $p?->unit ?? '';
                $pType = $p?->product_type;
                $qty = (float) $item->quantity;

                $isRefill = strtolower(trim((string) $pType)) === 'refill';

                if ($isRefill) {
                    $dailyData[$dateKey]['filling'] += (int) $qty;
                } else {
                    if ($pId) {
                        if (! isset($productsMap[$pId]) && $p) {
                            $productsMap[$pId] = [
                                'id' => $pId,
                                'name' => $pName,
                                'sku' => $pSku,
                                'product_type' => $pType ?? 'normal',
                            ];
                        }
                        if (! isset($dailyData[$dateKey]['products'][$pId])) {
                            $dailyData[$dateKey]['products'][$pId] = 0;
                        }
                        $dailyData[$dateKey]['products'][$pId] += (int) $qty;
                    }
                }
            }
        }

        foreach ($dealerPayments as $pay) {
            $dateKey = \Carbon\Carbon::parse($pay->payment_date)->toDateString();
            if (! isset($dailyData[$dateKey])) {
                $dailyData[$dateKey] = [
                    'date_raw' => $dateKey,
                    'filling' => 0,
                    'products' => [],
                    'cash' => 0.0,
                    'upi' => 0.0,
                ];
            }

            if ($pay->details && $pay->details->count() > 0) {
                foreach ($pay->details as $det) {
                    $mode = strtolower(trim($det->payment_mode ?? ''));
                    $amt = (float) $det->amount;
                    if ($mode === 'cash') {
                        $dailyData[$dateKey]['cash'] += $amt;
                    } elseif ($mode === 'upi') {
                        $dailyData[$dateKey]['upi'] += $amt;
                    }
                }
            } else {
                $mode = strtolower(trim($pay->payment_mode ?? $pay->payment_method ?? 'cash'));
                $amt = (float) $pay->amount;
                if ($mode === 'cash') {
                    $dailyData[$dateKey]['cash'] += $amt;
                } elseif ($mode === 'upi') {
                    $dailyData[$dateKey]['upi'] += $amt;
                }
            }
        }

        ksort($dailyData);

        $monthlyLedgerRows = [];
        $totalFilling = 0;
        $totalProductCounts = [];
        foreach ($productsMap as $pId => $prod) {
            $totalProductCounts[$pId] = 0;
        }
        $totalCash = 0.0;
        $totalUpi = 0.0;

        foreach ($dailyData as $dateKey => $data) {
            $filling = $data['filling'];
            $cash = round($data['cash'], 2);
            $upi = round($data['upi'], 2);
            $prodCounts = $data['products'];

            $hasActivity = ($filling > 0) || ($cash > 0) || ($upi > 0);
            if (! $hasActivity) {
                foreach ($prodCounts as $q) {
                    if ($q > 0) {
                        $hasActivity = true;
                        break;
                    }
                }
            }

            if (! $hasActivity) {
                continue;
            }

            $cDate = \Carbon\Carbon::parse($dateKey);
            $formattedDate = $cDate->format('d/m/Y');

            $totalFilling += $filling;
            $totalCash += $cash;
            $totalUpi += $upi;

            foreach ($productsMap as $pId => $pInfo) {
                $q = $prodCounts[$pId] ?? 0;
                $totalProductCounts[$pId] += $q;
            }

            $monthlyLedgerRows[] = [
                'date' => $formattedDate,
                'date_raw' => $dateKey,
                'filling' => $filling > 0 ? $filling : null,
                'products' => $prodCounts,
                'cash' => $cash > 0 ? $cash : null,
                'upi' => $upi > 0 ? $upi : null,
            ];
        }

        $monthlyTotals = [
            'filling' => $totalFilling,
            'products' => $totalProductCounts,
            'cash' => round($totalCash, 2),
            'upi' => round($totalUpi, 2),
        ];

        $companySetting = \App\Models\CompanySetting::first();

        return $this->successResponse([
            'dealer' => [
                'id' => $dealer->id,
                'name' => $dealer->name,
                'code' => $dealer->code,
                'contact_person' => $dealer->contact_person,
                'phone' => $dealer->phone,
                'email' => $dealer->email,
                'address' => $dealer->address,
                'gstin' => $dealer->gstin,
                'pan' => $dealer->pan,
            ],
            'company' => $companySetting ? [
                'name' => $companySetting->company_name ?? 'DockRent',
                'tagline' => $companySetting->tagline,
                'phone' => $companySetting->phone,
                'email' => $companySetting->email,
                'address' => $companySetting->address,
                'gstin' => $companySetting->gstin,
                'logo_path' => $companySetting->logo_path,
            ] : null,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'month' => $selMonth,
                'year' => $selYear,
            ],
            'summary' => [
                'opening_balance' => round($effectiveOpeningBalance, 2),
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'total_supply_amount' => round($totalSupplyAmount, 2),
                'total_payments_received' => round($totalPaymentsReceived, 2),
                'closing_balance' => round($closingBalance, 2),
                'debit_balance' => round($debitBalance, 2),
                'credit_balance' => round($creditBalance, 2),
                'current_outstanding' => $dealer->calculateOutstandingBalance(),
                'refill_count' => $refillCount,
                'refill_amount' => round($refillAmount, 2),
                'bottle_count' => $bottleCount,
                'bottle_amount' => round($bottleAmount, 2),
                'others_payments' => round($othersPayments, 2),
                'p1_refill_amount' => round($p1RefillAmount, 2),
                'p2_refill_amount' => round($p2RefillAmount, 2),
                'p1_payments' => round($p1Payments, 2),
                'p2_payments' => round($p2Payments, 2),
            ],
            'transactions' => $formattedEntries,
            'products' => array_values($productsMap),
            'monthly_ledger' => $monthlyLedgerRows,
            'monthly_totals' => $monthlyTotals,
        ], 'Dealer statement generated successfully.');
    }
}
