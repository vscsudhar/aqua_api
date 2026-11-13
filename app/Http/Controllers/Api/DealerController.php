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

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Base opening balance of dealer
        $baseOpening = (float) $dealer->opening_balance;

        // Calculate opening balance at start of range
        $effectiveOpeningBalance = $baseOpening;
        if ($startDate) {
            $priorDiff = DealerLedgerEntry::where('dealer_id', $id)
                ->where('entry_date', '<', $startDate)
                ->where('entry_type', '!=', 'opening_balance')
                ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance_diff')
                ->value('balance_diff') ?? 0;
            $effectiveOpeningBalance += (float) $priorDiff;
        }

        $query = DealerLedgerEntry::where('dealer_id', $id);
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
                'name' => $companySetting->company_name ?? 'SS AquaFlow',
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
            ],
            'transactions' => $formattedEntries,
        ], 'Dealer statement generated successfully.');
    }
}
