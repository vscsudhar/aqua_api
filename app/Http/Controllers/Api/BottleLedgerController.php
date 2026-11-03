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

    /**
     * Get monthly bottle ledger data (Left: Dealer Without Refill, Right: Supplier Purchases, Stock Register).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user?->company_id ?? 1;

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
     * Drill-down details for a specific day, product key, and source ('dealer' or 'supplier').
     */
    public function drilldown(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user?->company_id ?? 1;

        $request->validate([
            'date' => ['required', 'date'],
            'product_key' => ['required', 'string'],
            'source' => ['required', 'string', 'in:dealer,supplier'],
        ]);

        $date = Carbon::parse($request->input('date'))->toDateString();
        $productKey = strtoupper(trim($request->input('product_key')));
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
        $user = Auth::user();
        $companyId = $user?->company_id ?? 1;

        $now = Carbon::now('Asia/Kolkata');
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        $pdf = $this->bottleLedgerService->generatePdf($companyId, $year, $month);
        $filename = sprintf('bottle_ledger_%04d_%02d.pdf', $year, $month);

        return $pdf->download($filename);
    }
}

