<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DamagedProductResource;
use App\Models\CompanySetting;
use App\Models\DamagedProduct;
use App\Services\DamagedProductService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DamagedProductController extends Controller
{
    public function __construct(
        protected DamagedProductService $damagedProductService
    ) {}

    /**
     * List damaged products with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DamagedProduct::with(['product', 'creator', 'updater'])
            ->orderBy('damage_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->query('product_id'));
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->query('reason'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('damage_date', [
                $request->query('start_date'),
                $request->query('end_date'),
            ]);
        } elseif ($request->filled('date')) {
            $query->whereDate('damage_date', $request->query('date'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('damage_no', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $entries = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DamagedProductResource::collection($entries->items()),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    /**
     * Get next suggested damage entry number.
     */
    public function nextDamageNo(Request $request): JsonResponse
    {
        $date = $request->query('date');
        $damageNo = $this->damagedProductService->generateDamageNo(
            companyId: $request->user()?->company_id,
            date: $date
        );

        return response()->json([
            'success' => true,
            'damage_no' => $damageNo,
        ]);
    }

    /**
     * Summary KPI metrics (today, month, total, product breakdown).
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = $this->damagedProductService->getSummary(
            companyId: $request->user()?->company_id
        );

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Store a newly recorded damaged product entry.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'damage_date' => 'nullable|date',
            'damage_no' => 'nullable|string|max:50',
            'variant' => 'nullable|string|max:100',
            'reason' => 'required|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $entry = $this->damagedProductService->create($validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Damaged product recorded successfully and stock updated.',
            'data' => new DamagedProductResource($entry),
        ], 201);
    }

    /**
     * Display a specific damage entry.
     */
    public function show(int $id): JsonResponse
    {
        $entry = DamagedProduct::with(['product', 'creator', 'updater'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new DamagedProductResource($entry),
        ]);
    }

    /**
     * Update an existing damage entry and re-adjust stock.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $entry = DamagedProduct::findOrFail($id);

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'damage_date' => 'nullable|date',
            'variant' => 'nullable|string|max:100',
            'reason' => 'required|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $updatedEntry = $this->damagedProductService->update($entry, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Damaged product entry updated successfully and stock re-adjusted.',
            'data' => new DamagedProductResource($updatedEntry),
        ]);
    }

    /**
     * Delete a damage entry and restore stock to the product.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $entry = DamagedProduct::findOrFail($id);

        $this->damagedProductService->delete($entry, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Damaged product entry deleted and stock restored.',
        ]);
    }

    /**
     * Export Damaged Products history report as PDF.
     */
    public function exportPdf(Request $request): Response
    {
        $query = DamagedProduct::with(['product', 'creator'])
            ->orderBy('damage_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->query('product_id'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('damage_date', [
                $request->query('start_date'),
                $request->query('end_date'),
            ]);
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('damage_no', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $entries = $query->get();
        $totalQuantity = $entries->sum('quantity');
        $totalCost = $entries->sum('total_cost');

        $companyId = $request->user()?->company_id;
        $settings = CompanySetting::firstWhere('company_id', $companyId) ?? CompanySetting::first();

        $pdf = Pdf::loadView('reports.damaged_products_pdf', [
            'entries' => $entries,
            'totalQuantity' => $totalQuantity,
            'totalCost' => $totalCost,
            'settings' => $settings,
            'generatedAt' => Carbon::now('Asia/Kolkata')->format('d M Y, h:i A'),
            'filters' => [
                'start_date' => $request->query('start_date'),
                'end_date' => $request->query('end_date'),
            ],
        ])->setPaper('a4', 'landscape');

        return $pdf->download('Damaged_Products_Report.pdf');
    }
}
