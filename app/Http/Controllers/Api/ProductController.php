<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Unit;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'unitRel']);

        // 1. Text Search
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('hsn_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // 2. Category Filter
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        // 3. Unit Filter
        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->query('unit_id'));
        }

        // 4. Active Status Filter
        if ($request->has('is_active') && $request->query('is_active') !== '') {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        // 5. Low Stock Filter
        if ($request->boolean('low_stock')) {
            $query->where('track_stock', true)
                ->where('product_type', '!=', 'refill')
                ->whereColumn('current_stock', '<=', 'min_stock_level');
        }

        // 6. Product Type Filter (normal, refill)
        if ($request->filled('product_type')) {
            $query->where('product_type', $request->query('product_type'));
        }

        $query->orderBy('name', 'asc');

        if ($request->boolean('all')) {
            $products = $query->get();

            return $this->successResponse(ProductResource::collection($products), 'Products retrieved successfully.');
        }

        $perPage = (int) $request->query('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Products retrieved successfully.',
            'data' => ProductResource::collection($products->items()),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['product_type'] = $data['product_type'] ?? 'normal';

        if ($data['product_type'] === 'refill') {
            $data['track_stock'] = false;
            $data['current_stock'] = 0.00;
            $data['min_stock_level'] = null;
            $data['cost_price'] = null;
        }

        if (! empty($data['unit_id'])) {
            $unit = Unit::find($data['unit_id']);
            if ($unit) {
                $data['unit'] = $unit->name;
            }
        }

        $product = Product::create($data);

        return $this->successResponse(
            new ProductResource($product->load(['category', 'unitRel'])),
            'Product created successfully.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::with(['category', 'unitRel'])->find($id);

        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        return $this->successResponse(new ProductResource($product), 'Product retrieved successfully.');
    }

    public function update(ProductRequest $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        $data = $request->validated();
        $productType = $data['product_type'] ?? $product->product_type ?? 'normal';

        if ($productType === 'refill') {
            $data['product_type'] = 'refill';
            $data['track_stock'] = false;
            // Preserve hidden fields so they are not accidentally overwritten
            unset($data['cost_price']);
            unset($data['current_stock']);
            unset($data['min_stock_level']);
        }

        if (! empty($data['unit_id'])) {
            $unit = Unit::find($data['unit_id']);
            if ($unit) {
                $data['unit'] = $unit->name;
            }
        }

        $product->update($data);

        return $this->successResponse(
            new ProductResource($product->load(['category', 'unitRel'])),
            'Product updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        // Referential integrity check: do not delete products referenced by supplies or purchases
        if ($product->isReferencedInTransactions()) {
            $product->update(['is_active' => false]);

            return $this->successResponse(
                new ProductResource($product->load(['category', 'unitRel'])),
                'Product is referenced in recorded transactions and cannot be deleted. It has been deactivated instead.'
            );
        }

        $product->delete();

        return $this->successResponse(null, 'Product deleted successfully.');
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        $product->update(['is_active' => ! $product->is_active]);

        $statusLabel = $product->is_active ? 'activated' : 'deactivated';

        return $this->successResponse(
            new ProductResource($product->load(['category', 'unitRel'])),
            "Product {$statusLabel} successfully."
        );
    }
}
