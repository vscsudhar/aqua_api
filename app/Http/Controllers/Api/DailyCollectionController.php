<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StoreDealerPaymentRequest;
use App\Http\Requests\Supply\StoreDealerSupplyRequest;
use App\Http\Requests\Supply\UpdateDealerSupplyRequest;
use App\Http\Resources\DealerPaymentResource;
use App\Http\Resources\DealerSupplyResource;
use App\Models\DealerPayment;
use App\Models\DealerSupply;
use App\Services\DailyCollectionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyCollectionController extends Controller
{
    public function __construct(
        protected DailyCollectionService $collectionService
    ) {}

    /**
     * Daily summary metrics for selected date.
     */
    public function summary(Request $request): JsonResponse
    {
        $date = $request->query('date', Carbon::today('Asia/Kolkata')->toDateString());
        $summary = $this->collectionService->getDailySummary($date);

        return response()->json([
            'status' => 'success',
            'message' => 'Daily collection summary retrieved.',
            'data' => $summary,
        ]);
    }

    /**
     * Dealer list for daily supply & collection management.
     */
    public function dealers(Request $request): JsonResponse
    {
        $date = $request->query('date', Carbon::today('Asia/Kolkata')->toDateString());
        $search = $request->query('search');
        $statusFilter = $request->query('status'); // supplied, pending, paid, no_supply

        $dealers = $this->collectionService->getDailyDealers($date, $search, $statusFilter);

        return response()->json([
            'status' => 'success',
            'message' => 'Daily dealers list retrieved.',
            'data' => $dealers,
        ]);
    }

    /**
     * List supply transactions with filters and pagination.
     */
    public function suppliesIndex(Request $request): JsonResponse
    {
        $query = DealerSupply::with(['dealer', 'items.product', 'payments'])
            ->orderBy('supply_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->has('dealer_id')) {
            $query->where('dealer_id', $request->query('dealer_id'));
        }

        if ($request->has('date')) {
            $query->whereDate('supply_date', $request->query('date'));
        } elseif ($request->has('month') && $request->has('year')) {
            $query->whereYear('supply_date', $request->query('year'))
                ->whereMonth('supply_date', $request->query('month'));
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->query('payment_status'));
        }

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('supply_no', 'like', "%{$search}%")
                    ->orWhereHas('dealer', function ($dq) use ($search) {
                        $dq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $supplies = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Supplies retrieved successfully.',
            'data' => DealerSupplyResource::collection($supplies),
            'pagination' => [
                'current_page' => $supplies->currentPage(),
                'last_page' => $supplies->lastPage(),
                'per_page' => $supplies->perPage(),
                'total' => $supplies->total(),
            ],
        ]);
    }

    /**
     * Create a new dealer supply.
     */
    public function suppliesStore(StoreDealerSupplyRequest $request): JsonResponse
    {
        $supply = $this->collectionService->createSupply($request->validated(), $request->user());
        $isNew = $supply->wasRecentlyCreated;

        return response()->json([
            'status' => 'success',
            'message' => $isNew ? 'Dealer supply recorded successfully.' : 'Existing supply retrieved (idempotent).',
            'data' => new DealerSupplyResource($supply),
        ], $isNew ? 201 : 200);
    }

    /**
     * Show single supply transaction with audit info.
     */
    public function suppliesShow(int $id): JsonResponse
    {
        $supply = DealerSupply::with(['dealer', 'items.product', 'payments', 'creator', 'updater'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Supply details retrieved.',
            'data' => new DealerSupplyResource($supply),
        ]);
    }

    /**
     * Edit supply transaction.
     */
    public function suppliesUpdate(UpdateDealerSupplyRequest $request, int $id): JsonResponse
    {
        $supply = DealerSupply::findOrFail($id);
        $updated = $this->collectionService->updateSupply($supply, $request->validated(), $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Supply transaction updated successfully.',
            'data' => new DealerSupplyResource($updated),
        ]);
    }

    /**
     * List payment transactions.
     */
    public function paymentsIndex(Request $request): JsonResponse
    {
        $query = DealerPayment::with(['dealer', 'supply', 'collector'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->has('dealer_id')) {
            $query->where('dealer_id', $request->query('dealer_id'));
        }

        if ($request->has('date')) {
            $query->whereDate('payment_date', $request->query('date'));
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->query('payment_method'));
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $payments = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Payments retrieved successfully.',
            'data' => DealerPaymentResource::collection($payments),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Record a collection payment.
     */
    public function paymentsStore(StoreDealerPaymentRequest $request): JsonResponse
    {
        $payment = $this->collectionService->recordPayment($request->validated(), $request->user());
        $isNew = $payment->wasRecentlyCreated;

        return response()->json([
            'status' => 'success',
            'message' => $isNew ? 'Payment collection recorded successfully.' : 'Existing payment retrieved (idempotent).',
            'data' => new DealerPaymentResource($payment),
        ], $isNew ? 201 : 200);
    }
}
