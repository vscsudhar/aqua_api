<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\DealerPriceRequest;
use App\Http\Resources\DealerPriceResource;
use App\Models\Dealer;
use App\Models\DealerProductPrice;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealerPriceController extends Controller
{
    use ApiResponseTrait;

    public function index(int $dealerId): JsonResponse
    {
        $dealer = Dealer::find($dealerId);

        if (! $dealer) {
            return $this->errorResponse('Dealer not found.', 404);
        }

        // Custom prices for this dealer
        $customPrices = DealerProductPrice::with(['product.category', 'product.unitRel'])
            ->where('dealer_id', $dealerId)
            ->orderBy('is_active', 'desc')
            ->orderBy('effective_from', 'desc')
            ->get();

        // Catalog products with effective price status
        $allProducts = Product::with(['category', 'unitRel'])
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        $pricingOverview = $allProducts->map(function ($product) use ($dealer) {
            $effective = $dealer->getEffectiveProductPrice($product->id);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'category_name' => $product->category?->name ?? 'General',
                'unit_name' => $product->unitRel?->name ?? $product->unit ?? 'Unit',
                'unit_code' => $product->unitRel?->code ?? '',
                'catalog_price' => (float) $product->default_price,
                'effective_price' => $effective['price'],
                'is_custom' => $effective['is_custom'],
                'custom_price_id' => $effective['custom_price_id'],
                'effective_from' => $effective['effective_from'],
                'effective_to' => $effective['effective_to'],
            ];
        });

        return $this->successResponse([
            'dealer_id' => $dealer->id,
            'dealer_name' => $dealer->name,
            'dealer_code' => $dealer->code,
            'custom_prices' => DealerPriceResource::collection($customPrices),
            'catalog_matrix' => $pricingOverview,
        ], 'Dealer product pricing retrieved successfully.');
    }

    public function store(DealerPriceRequest $request, int $dealerId): JsonResponse
    {
        $dealer = Dealer::find($dealerId);

        if (! $dealer) {
            return $this->errorResponse('Dealer not found.', 404);
        }

        $data = $request->validated();
        $data['dealer_id'] = $dealerId;
        $data['created_by'] = auth()->id();
        $data['is_active'] = $data['is_active'] ?? true;

        $newEffectiveFrom = Carbon::parse($data['effective_from'])->toDateString();

        // Preserve Price History:
        // If an active custom price already exists for this dealer + product,
        // set its effective_to = new effective_from - 1 day
        DealerProductPrice::where('dealer_id', $dealerId)
            ->where('product_id', $data['product_id'])
            ->where('is_active', true)
            ->where(function ($q) use ($newEffectiveFrom) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $newEffectiveFrom);
            })
            ->update([
                'effective_to' => Carbon::parse($newEffectiveFrom)->subDay()->toDateString(),
            ]);

        $dealerPrice = DealerProductPrice::create($data);

        return $this->successResponse(
            new DealerPriceResource($dealerPrice->load(['product.category', 'product.unitRel'])),
            'Dealer custom product price configured successfully.',
            201
        );
    }

    public function destroy(int $dealerId, int $priceId): JsonResponse
    {
        $dealerPrice = DealerProductPrice::where('dealer_id', $dealerId)->find($priceId);

        if (! $dealerPrice) {
            return $this->errorResponse('Dealer price configuration not found.', 404);
        }

        $dealerPrice->delete();

        return $this->successResponse(null, 'Dealer custom price removed successfully.');
    }

    public function resolvePrice(int $dealerId, Request $request): JsonResponse
    {
        $dealer = Dealer::find($dealerId);

        if (! $dealer) {
            return $this->errorResponse('Dealer not found.', 404);
        }

        $productId = (int) $request->query('product_id');
        if (! $productId) {
            return $this->errorResponse('Product ID is required.', 422);
        }

        $date = $request->query('date');
        $resolved = $dealer->getEffectiveProductPrice($productId, $date);

        return $this->successResponse($resolved, 'Effective product price resolved.');
    }
}
