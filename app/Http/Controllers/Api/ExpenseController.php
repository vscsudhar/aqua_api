<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    use ApiResponseTrait;

    protected ExpenseService $service;

    public function __construct(ExpenseService $service)
    {
        $this->service = $service;
    }

    protected function resolveCompanyId(Request $request): int
    {
        $user = $request->user() ?: Auth::user();

        return (int) ($user?->company_id ?: 1);
    }

    // ==========================================
    // Expense Categories
    // ==========================================

    public function categoriesIndex(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $includeInactive = $request->boolean('include_inactive', true);
        $categories = $this->service->getCategories($companyId, $includeInactive);

        return $this->successResponse($categories, 'Expense categories retrieved successfully.');
    }

    public function categoriesStore(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $category = $this->service->createCategory($companyId, $validated);

        return $this->successResponse($category, 'Expense category created successfully.', 201);
    }

    public function categoriesUpdate(Request $request, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'description' => 'nullable|string|max:255',
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
        ]);

        $category = $this->service->updateCategory($companyId, $id, $validated);

        return $this->successResponse($category, 'Expense category updated successfully.');
    }

    public function categoriesDestroy(Request $request, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $this->service->deleteCategory($companyId, $id);

        return $this->successResponse(null, 'Expense category deleted successfully.');
    }

    // ==========================================
    // Expenses CRUD
    // ==========================================

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $query = Expense::withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->where('company_id', $companyId)
            ->with(['category', 'creator']);

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('expense_date', [
                $request->input('from_date'),
                $request->input('to_date'),
            ]);
        } elseif ($request->filled('from_date')) {
            $query->whereDate('expense_date', '>=', $request->input('from_date'));
        } elseif ($request->filled('to_date')) {
            $query->whereDate('expense_date', '<=', $request->input('to_date'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        if ($request->filled('payment_mode')) {
            $query->where('payment_mode', $request->input('payment_mode'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('paid_to', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('payment_mode', 'like', "%{$search}%")
                  ->orWhereHas('category', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $query->orderBy('expense_date', 'desc')->orderBy('id', 'desc');

        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Expenses retrieved successfully.',
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

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'category_id' => 'required|integer',
            'amount' => 'required|numeric|gt:0|max:999999999.99',
            'expense_date' => 'required|date_format:Y-m-d',
            'expense_time' => 'nullable|string|max:20',
            'payment_mode' => ['required', Rule::in(ExpenseService::PAYMENT_MODES)],
            'paid_to' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|mimetypes:image/jpeg,image/png,image/webp,application/pdf|max:5120',
        ]);

        $expense = $this->service->createExpense(
            $companyId,
            $validated,
            $request->file('attachment'),
            $request->user()
        );

        return $this->successResponse($expense, 'Expense created successfully.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $expense = Expense::withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->where('company_id', $companyId)
            ->with(['category', 'creator'])
            ->findOrFail($id);

        return $this->successResponse($expense, 'Expense details retrieved successfully.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $expense = Expense::withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'sometimes|required|integer',
            'amount' => 'sometimes|required|numeric|gt:0|max:999999999.99',
            'expense_date' => 'sometimes|required|date_format:Y-m-d',
            'expense_time' => 'nullable|string|max:20',
            'payment_mode' => ['sometimes', 'required', Rule::in(ExpenseService::PAYMENT_MODES)],
            'paid_to' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:1000',
            'remove_attachment' => 'nullable|boolean',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|mimetypes:image/jpeg,image/png,image/webp,application/pdf|max:5120',
        ]);

        $updated = $this->service->updateExpense(
            $expense,
            $validated,
            $request->file('attachment'),
            $request->boolean('remove_attachment', false),
            $request->user()
        );

        return $this->successResponse($updated, 'Expense updated successfully.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $expense = Expense::withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $this->service->deleteExpense($expense, $request->user());

        return $this->successResponse(null, 'Expense deleted successfully.');
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));
        $expenses = Expense::withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->get();

        $deletedCount = 0;
        \Illuminate\Support\Facades\DB::transaction(function () use ($expenses, $request, &$deletedCount) {
            foreach ($expenses as $expense) {
                $this->service->deleteExpense($expense, $request->user());
                $deletedCount++;
            }
        });

        return $this->successResponse(
            ['deleted_count' => $deletedCount],
            "{$deletedCount} expense(s) deleted successfully."
        );
    }

    // ==========================================
    // Private Attachment Download / Viewing
    // ==========================================

    public function downloadAttachment(Request $request, int $id): StreamedResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $expense = Expense::withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->where('company_id', $companyId)
            ->findOrFail($id);

        if (! $expense->attachment_path || ! Storage::disk('local')->exists($expense->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        $fileName = $expense->attachment_original_name ?: basename($expense->attachment_path);

        return Storage::disk('local')->response($expense->attachment_path, $fileName);
    }

    // ==========================================
    // Summary & Reports
    // ==========================================

    public function summary(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $request->validate([
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
            'category_id' => 'nullable|integer',
            'payment_mode' => ['nullable', Rule::in(ExpenseService::PAYMENT_MODES)],
        ]);

        $data = $this->service->getSummary(
            $companyId,
            $request->input('from_date'),
            $request->input('to_date'),
            $request->filled('category_id') ? (int) $request->input('category_id') : null,
            $request->input('payment_mode')
        );

        return $this->successResponse($data, 'Expense summary retrieved successfully.');
    }

    public function report(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $request->validate([
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
            'category_id' => 'nullable|integer',
            'payment_mode' => ['nullable', Rule::in(ExpenseService::PAYMENT_MODES)],
        ]);

        $data = $this->service->getReport(
            $companyId,
            $request->input('from_date'),
            $request->input('to_date'),
            $request->filled('category_id') ? (int) $request->input('category_id') : null,
            $request->input('payment_mode')
        );

        return $this->successResponse($data, 'Expense report retrieved successfully.');
    }
}
