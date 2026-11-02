<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\ProductCategory;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $query = ProductCategory::withCount('products');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->query('is_active') !== '') {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('name', 'asc');

        if ($request->boolean('all')) {
            $categories = $query->get();

            return $this->successResponse(CategoryResource::collection($categories), 'Categories retrieved successfully.');
        }

        $perPage = (int) $request->query('per_page', 15);
        $categories = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Categories retrieved successfully.',
            'data' => CategoryResource::collection($categories->items()),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'last_page' => $categories->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = ProductCategory::create($request->validated());

        return $this->successResponse(
            new CategoryResource($category->loadCount('products')),
            'Category created successfully.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $category = ProductCategory::withCount('products')->find($id);

        if (! $category) {
            return $this->errorResponse('Category not found.', 404);
        }

        return $this->successResponse(new CategoryResource($category), 'Category retrieved successfully.');
    }

    public function update(CategoryRequest $request, int $id): JsonResponse
    {
        $category = ProductCategory::find($id);

        if (! $category) {
            return $this->errorResponse('Category not found.', 404);
        }

        $category->update($request->validated());

        return $this->successResponse(
            new CategoryResource($category->loadCount('products')),
            'Category updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $category = ProductCategory::withCount('products')->find($id);

        if (! $category) {
            return $this->errorResponse('Category not found.', 404);
        }

        if ($category->products_count > 0) {
            // Cannot delete category that contains products; deactivate instead
            $category->update(['is_active' => false]);

            return $this->successResponse(
                new CategoryResource($category),
                'Category has active products. It has been deactivated instead of deleted.'
            );
        }

        $category->delete();

        return $this->successResponse(null, 'Category deleted successfully.');
    }
}
