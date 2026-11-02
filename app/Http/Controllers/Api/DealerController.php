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
}
