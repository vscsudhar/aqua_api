<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyOtherTransaction;
use App\Services\DealerLedgerReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealerLedgerReportController extends Controller
{
    public function __construct(
        protected DealerLedgerReportService $reportService
    ) {}

    /**
     * Retrieves the complete monthly dealer ledger matrix data.
     */
    public function index(Request $request): JsonResponse
    {
        $now = Carbon::now('Asia/Kolkata');
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        $dealerIds = $request->input('dealer_ids');
        if (is_string($dealerIds)) {
            $dealerIds = array_filter(array_map('intval', explode(',', $dealerIds)));
        } elseif (! is_array($dealerIds)) {
            $dealerIds = [];
        }

        $caneProductId = $request->filled('cane_product_id') ? (int) $request->input('cane_product_id') : null;

        $report = $this->reportService->generateMonthlyLedger($year, $month, $dealerIds, $caneProductId);

        return response()->json([
            'success' => true,
            'message' => 'Monthly dealer ledger report generated successfully.',
            'data' => $report,
        ]);
    }

    /**
     * Gets daily others transactions for a given month.
     */
    public function getOthers(Request $request): JsonResponse
    {
        $now = Carbon::now('Asia/Kolkata');
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        $startDate = Carbon::createFromDate($year, $month, 1, 'Asia/Kolkata')->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->startOfDay();

        $others = DailyOtherTransaction::with(['creator:id,name', 'updater:id,name'])
            ->whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('transaction_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $others,
        ]);
    }

    /**
     * Records or updates a daily Others entry with audit logging.
     */
    public function storeOthers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'cane_quantity' => ['nullable', 'integer', 'min:0'],
            'filling_amount' => ['nullable', 'numeric', 'min:0'],
            'payments_received' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $record = $this->reportService->upsertOtherTransaction($validated, auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Daily Others entry saved successfully.',
            'data' => [
                'id' => (int) $record->id,
                'transaction_date' => Carbon::parse($record->transaction_date)->toDateString(),
                'period' => (int) $record->period,
                'cane_quantity' => (int) $record->cane_quantity,
                'filling_amount' => (float) $record->filling_amount,
                'payments_received' => (float) $record->payments_received,
                'notes' => $record->notes,
                'created_by' => $record->created_by,
                'updated_by' => $record->updated_by,
            ],
        ]);
    }

    /**
     * Exports A4 landscape PDF.
     */
    public function exportPdf(Request $request)
    {
        $now = Carbon::now('Asia/Kolkata');
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        $dealerIds = $request->input('dealer_ids');
        if (is_string($dealerIds)) {
            $dealerIds = array_filter(array_map('intval', explode(',', $dealerIds)));
        } elseif (! is_array($dealerIds)) {
            $dealerIds = [];
        }

        $caneProductId = $request->filled('cane_product_id') ? (int) $request->input('cane_product_id') : null;

        return $this->reportService->exportPdf($year, $month, $dealerIds, $caneProductId);
    }

    /**
     * Exports Excel (.xlsx).
     */
    public function exportExcel(Request $request)
    {
        $now = Carbon::now('Asia/Kolkata');
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        $dealerIds = $request->input('dealer_ids');
        if (is_string($dealerIds)) {
            $dealerIds = array_filter(array_map('intval', explode(',', $dealerIds)));
        } elseif (! is_array($dealerIds)) {
            $dealerIds = [];
        }

        $caneProductId = $request->filled('cane_product_id') ? (int) $request->input('cane_product_id') : null;

        return $this->reportService->exportExcel($year, $month, $dealerIds, $caneProductId);
    }

    /**
     * Exports CSV.
     */
    public function exportCsv(Request $request)
    {
        $now = Carbon::now('Asia/Kolkata');
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        $dealerIds = $request->input('dealer_ids');
        if (is_string($dealerIds)) {
            $dealerIds = array_filter(array_map('intval', explode(',', $dealerIds)));
        } elseif (! is_array($dealerIds)) {
            $dealerIds = [];
        }

        $caneProductId = $request->filled('cane_product_id') ? (int) $request->input('cane_product_id') : null;

        return $this->reportService->exportCsv($year, $month, $dealerIds, $caneProductId);
    }
}
