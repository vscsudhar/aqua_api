<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $query = Unit::withCount('products');

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
            $units = $query->get();

            return $this->successResponse(UnitResource::collection($units), 'Units retrieved successfully.');
        }

        $perPage = (int) $request->query('per_page', 15);
        $units = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Units retrieved successfully.',
            'data' => UnitResource::collection($units->items()),
            'meta' => [
                'current_page' => $units->currentPage(),
                'per_page' => $units->perPage(),
                'total' => $units->total(),
                'last_page' => $units->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    public function store(UnitRequest $request): JsonResponse
    {
        $unit = Unit::create($request->validated());

        return $this->successResponse(
            new UnitResource($unit->loadCount('products')),
            'Measurement unit created successfully.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $unit = Unit::withCount('products')->find($id);

        if (! $unit) {
            return $this->errorResponse('Unit not found.', 404);
        }

        return $this->successResponse(new UnitResource($unit), 'Unit retrieved successfully.');
    }

    public function update(UnitRequest $request, int $id): JsonResponse
    {
        $unit = Unit::find($id);

        if (! $unit) {
            return $this->errorResponse('Unit not found.', 404);
        }

        $unit->update($request->validated());

        return $this->successResponse(
            new UnitResource($unit->loadCount('products')),
            'Unit updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $unit = Unit::withCount('products')->find($id);

        if (! $unit) {
            return $this->errorResponse('Unit not found.', 404);
        }

        if ($unit->products_count > 0) {
            $unit->update(['is_active' => false]);

            return $this->successResponse(
                new UnitResource($unit),
                'Unit is assigned to existing products. It has been deactivated instead of deleted.'
            );
        }

        $unit->delete();

        return $this->successResponse(null, 'Unit deleted successfully.');
    }
}
