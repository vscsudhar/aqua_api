<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BottleLedgerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BottleLedgerController extends Controller
{
    public function __construct(
        protected BottleLedgerService $bottleLedgerService
    ) {}

    protected function resolveCompanyId(Request $request): int
    {
        $user = $request->user() ?: Auth::user();
        if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return (int) ($request->header('X-Company-Id') ?: $request->input('company_id') ?: $user->company_id ?: 1);
        }

        $homeId = (int) ($user?->company_id ?: 1);
        $requested = $request->header('X-Company-Id') ?: $request->input('company_id');
        if ($requested && (int) $requested !== $homeId) {
            $isOwned = \App\Models\Company::withoutGlobalScopes()
                ->where('id', $requested)
                ->where('owner_company_id', $homeId)
                ->exists();

            if ($isOwned) {
                return (int) $requested;
            }
        }

        return $homeId;
    }

    /**
     * Get monthly bottle ledger data (Left: Dealer Without Refill, Right: Supplier Purchases, Stock Register).
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $now = Carbon::now('Asia/Kolkata');
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        $report = $this->bottleLedgerService->getMonthlyLedger($companyId, $year, $month);

        return response()->json([
            'success' => true,
            'message' => 'Bottle ledger data retrieved successfully.',
            'data' => $report,
        ]);
    }

    /**
     * Drill-down details for a specific day, product key, and source ('dealer', 'supplier', or 'damage').
     */
    public function drilldown(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $request->validate([
            'date' => ['required', 'date'],
            'product_key' => ['required', 'string'],
            'source' => ['required', 'string', 'in:dealer,supplier,damage'],
        ]);

        $date = Carbon::parse($request->input('date'))->toDateString();
        $productKey = trim($request->input('product_key'));
        $source = strtolower($request->input('source'));

        $details = $this->bottleLedgerService->getTransactionDetails($companyId, $date, $productKey, $source);

        return response()->json([
            'success' => true,
            'data' => $details,
        ]);
    }

    /**
     * Export traditional physical ledger sheet A4 landscape PDF.
     */
    public function exportPdf(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $now = Carbon::now('Asia/Kolkata');
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        $pdf = $this->bottleLedgerService->generatePdf($companyId, $year, $month);
        $filename = sprintf('bottle_ledger_%04d_%02d.pdf', $year, $month);

        return $pdf->download($filename);
    }

    /**
     * Get eligible Without Refill products for Others Entry dropdown.
     */
    public function getProducts(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $products = $this->bottleLedgerService->getWithoutRefillProducts($companyId);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Get Others Statement date-wise and entries list for a month.
     */
    public function getOthersStatement(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $now = Carbon::now('Asia/Kolkata');
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        $statement = $this->bottleLedgerService->getOthersStatement($companyId, $year, $month);

        return response()->json([
            'success' => true,
            'data' => $statement,
        ]);
    }

    /**
     * Record a new Bottle Other Entry.
     */
    public function storeOtherEntry(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $entry = $this->bottleLedgerService->createOtherEntry($companyId, $validated, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Others bottle entry created successfully.',
            'data' => $entry->load('product'),
        ], 201);
    }

    /**
     * Update an existing Bottle Other Entry.
     */
    public function updateOtherEntry(Request $request, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'entry_date' => ['sometimes', 'required', 'date'],
            'product_id' => ['sometimes', 'required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $entry = $this->bottleLedgerService->updateOtherEntry($companyId, $id, $validated, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Others bottle entry updated successfully.',
            'data' => $entry->load('product'),
        ]);
    }

    /**
     * Delete an existing Bottle Other Entry.
     */
    public function destroyOtherEntry(Request $request, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $this->bottleLedgerService->deleteOtherEntry($companyId, $id);

        return response()->json([
            'success' => true,
            'message' => 'Others bottle entry deleted successfully.',
        ]);
    }

    /**
     * Export Others Statement A4 portrait PDF.
     */
    public function exportOthersPdf(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $now = Carbon::now('Asia/Kolkata');
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        $pdf = $this->bottleLedgerService->generateOthersStatementPdf($companyId, $year, $month);
        $filename = sprintf('bottle_others_statement_%04d_%02d.pdf', $year, $month);

        return $pdf->download($filename);
    }

    /**
     * Get pre-deletion financial and payment adjustment breakdown.
     */
    public function deleteBreakdown(Request $request, string $type, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        if (! in_array($type, ['dealer_supply_item', 'others_entry'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid transaction type specified.',
            ], 422);
        }

        try {
            $data = $this->bottleLedgerService->getDeleteBreakdown($companyId, $type, $id);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Delete a bottle purchase entry from Dealer Bottle Purchase drill-down,
     * synchronizing Daily Collection and Other Statement.
     */
    public function destroyTransaction(Request $request, string $type, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        if (! in_array($type, ['dealer_supply_item', 'others_entry'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid transaction type specified.',
            ], 422);
        }

        $cashDeduction = (float) $request->input('cash_deduction', 0.0);
        $upiDeduction = (float) $request->input('upi_deduction', 0.0);

        try {
            $result = $this->bottleLedgerService->deleteBottlePurchaseEntry(
                $companyId,
                $type,
                $id,
                $cashDeduction,
                $upiDeduction,
                $request->user()
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
