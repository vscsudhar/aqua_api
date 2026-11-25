<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\PublicParty;
use App\Models\PublicSale;
use App\Services\PublicSalesService;
use App\Traits\ApiResponseTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PublicSalesController extends Controller
{
    use ApiResponseTrait;

    protected PublicSalesService $service;

    public function __construct(PublicSalesService $service)
    {
        $this->service = $service;
    }

    protected function resolveCompanyId(Request $request): int
    {
        $user = $request->user() ?: Auth::user();
        if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return (int) ($request->header('X-Company-Id') ?: $request->input('company_id') ?: $user->company_id ?: 1);
        }

        $homeId = (int) ($user?->company_id ?: 1);
        $requested = $request->header('X-Company-Id') ?: $request->input('company_id');
        if ($requested && (int) $requested !== $homeId) {
            $isOwned = Company::withoutGlobalScopes()
                ->where('id', $requested)
                ->where('owner_company_id', $homeId)
                ->exists();

            if ($isOwned) {
                return (int) $requested;
            }
        }

        return $homeId;
    }

    // ==========================================
    // 1. Party Management
    // ==========================================

    public function partiesIndex(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $search = $request->query('search');
        $status = $request->query('status');

        $parties = $this->service->listParties($companyId, $search, $status);

        return $this->successResponse($parties, 'Public parties retrieved successfully.');
    }

    public function partiesStore(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'party_name' => 'required|string|max:191',
            'contact_number' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $party = $this->service->createParty($companyId, $validated);

        return $this->successResponse($party, 'Public party created successfully.', 201);
    }

    public function partiesUpdate(Request $request, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $party = PublicParty::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $validated = $request->validate([
            'party_name' => 'sometimes|required|string|max:191',
            'contact_number' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $party = $this->service->updateParty($party, $validated);

        return $this->successResponse($party, 'Public party updated successfully.');
    }

    public function partiesDestroy(Request $request, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $party = PublicParty::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        try {
            $this->service->deleteParty($party);

            return $this->successResponse(null, 'Public party deleted successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        }
    }

    // ==========================================
    // 2. Product Catalog Integration
    // ==========================================

    public function productsIndex(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $products = $this->service->getEligibleProducts($companyId);

        $data = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'product_type' => $p->product_type ?? 'normal',
                'unit' => $p->unit ?? 'Unit',
                'default_price' => (float) $p->default_price,
                'current_stock' => $p->current_stock !== null ? (float) $p->current_stock : null,
                'track_stock' => (bool) $p->track_stock,
                'inventory_start_date' => $p->inventory_start_date ? $p->inventory_start_date->format('Y-m-d') : null,
            ];
        });

        return $this->successResponse($data, 'Eligible products retrieved successfully.');
    }

    // ==========================================
    // 3. Public Sales Entries
    // ==========================================

    public function salesIndex(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $query = PublicSale::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->with(['party', 'items.product', 'creator']);

        if ($request->filled('party_id')) {
            $query->where('party_id', (int) $request->input('party_id'));
        }

        if ($request->filled('sale_date')) {
            $query->whereDate('sale_date', $request->input('sale_date'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('sale_date', [
                $request->input('start_date'),
                $request->input('end_date'),
            ]);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('party', function ($q) use ($search) {
                $q->where('party_name', 'like', "%{$search}%");
            });
        }

        $query->orderBy('sale_date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($request->boolean('all')) {
            $sales = $query->get();
            return $this->successResponse($sales, 'Public sales retrieved successfully.');
        }

        $perPage = (int) $request->input('per_page', 20);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Public sales retrieved successfully.',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    public function salesStore(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'party_id' => 'required|integer',
            'sale_date' => 'required|date',
            'cash_amount' => 'nullable|numeric|min:0',
            'upi_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'confirm_insufficient_stock' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.amount' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        try {
            $sale = $this->service->createSale($companyId, $validated, $request->user());

            return $this->successResponse($sale, 'Public sales entry created successfully.', 201);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $statusCode = isset($errors['stock_insufficient']) ? 409 : 422;

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => $errors,
                'is_stock_insufficient' => isset($errors['stock_insufficient']),
                'insufficient_items' => $errors['insufficient_items'] ?? [],
            ], $statusCode);
        }
    }

    public function salesShow(Request $request, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $sale = PublicSale::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->with(['party', 'items.product', 'creator'])
            ->findOrFail($id);

        return $this->successResponse($sale, 'Public sales entry details retrieved successfully.');
    }

    public function salesUpdate(Request $request, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $sale = PublicSale::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $validated = $request->validate([
            'party_id' => 'sometimes|required|integer',
            'sale_date' => 'sometimes|required|date',
            'cash_amount' => 'nullable|numeric|min:0',
            'upi_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'confirm_insufficient_stock' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.amount' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        try {
            $updated = $this->service->updateSale($sale, $validated, $request->user());

            return $this->successResponse($updated, 'Public sales entry updated successfully.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $statusCode = isset($errors['stock_insufficient']) ? 409 : 422;

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => $errors,
                'is_stock_insufficient' => isset($errors['stock_insufficient']),
                'insufficient_items' => $errors['insufficient_items'] ?? [],
            ], $statusCode);
        }
    }

    public function salesDestroy(Request $request, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $sale = PublicSale::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $this->service->deleteSale($sale, $request->user());

        return $this->successResponse(null, 'Public sales entry deleted successfully.');
    }

    // ==========================================
    // 4. Statements & Daily Details
    // ==========================================

    public function monthlyStatement(Request $request, int $partyId): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $now = Carbon::now('Asia/Kolkata');
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        $data = $this->service->getPartyMonthlyStatement($companyId, $partyId, $year, $month);

        return $this->successResponse($data, 'Party monthly statement retrieved successfully.');
    }

    public function dailyDetails(Request $request, int $partyId): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $date = $request->input('date', Carbon::today('Asia/Kolkata')->toDateString());

        $data = $this->service->getPartyDailyDetails($companyId, $partyId, $date);

        return $this->successResponse($data, 'Party daily sales details retrieved successfully.');
    }

    // ==========================================
    // 5. PDF Printing Endpoints
    // ==========================================

    public function exportMonthlyStatementPdf(Request $request, int $partyId): Response
    {
        $companyId = $this->resolveCompanyId($request);
        $now = Carbon::now('Asia/Kolkata');
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        $statement = $this->service->getPartyMonthlyStatement($companyId, $partyId, $year, $month);
        $companyLogo = $this->service->getCompanyLogoForPdf($companyId);

        $pdf = Pdf::loadView('reports.public_sales_monthly_statement_pdf', [
            'statement' => $statement,
            'companyLogo' => $companyLogo,
            'generatedAt' => Carbon::now('Asia/Kolkata')->format('d M Y, h:i A'),
        ])->setPaper('a4', 'landscape')
          ->setOption([
              'isRemoteEnabled' => true,
              'isHtml5ParserEnabled' => true,
              'chroot' => [public_path(), base_path(), storage_path()],
          ]);

        $fileName = sprintf('Public_Sales_Statement_%s_%s_%04d_%02d.pdf',
            str_replace(' ', '_', $statement['party']['party_name']),
            $statement['period']['month_name'],
            $year,
            $month
        );

        return $pdf->download($fileName);
    }

    public function exportDailyStatementPdf(Request $request, int $partyId): Response
    {
        $companyId = $this->resolveCompanyId($request);
        $date = $request->input('date', Carbon::today('Asia/Kolkata')->toDateString());

        $details = $this->service->getPartyDailyDetails($companyId, $partyId, $date);
        $companyLogo = $this->service->getCompanyLogoForPdf($companyId);

        $pdf = Pdf::loadView('reports.public_sales_daily_statement_pdf', [
            'details' => $details,
            'companyLogo' => $companyLogo,
            'generatedAt' => Carbon::now('Asia/Kolkata')->format('d M Y, h:i A'),
        ])->setPaper('a4', 'portrait')
          ->setOption([
              'isRemoteEnabled' => true,
              'isHtml5ParserEnabled' => true,
              'chroot' => [public_path(), base_path(), storage_path()],
          ]);

        $fileName = sprintf('Public_Sales_Daily_%s_%s.pdf',
            str_replace(' ', '_', $details['party']['party_name']),
            $date
        );

        return $pdf->download($fileName);
    }
}
