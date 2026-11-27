<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public const PAYMENT_MODES = ['Cash', 'UPI', 'Bank'];

    /**
     * Ensure default categories exist if seeder hasn't been run yet.
     */
    public function ensureDefaultCategoriesSeeded(): void
    {
        if (! ExpenseCategory::whereNull('company_id')->exists()) {
            (new \Database\Seeders\ExpenseCategorySeeder())->run();
        }
    }

    public function getCategories(int $companyId, bool $includeInactive = true)
    {
        $this->ensureDefaultCategoriesSeeded();

        $query = ExpenseCategory::accessibleToCompany($companyId)
            ->orderByDesc('is_default')
            ->orderBy('name');

        if (! $includeInactive) {
            $query->where('status', 'active');
        }

        return $query->get();
    }

    public function createCategory(int $companyId, array $data): ExpenseCategory
    {
        $name = trim((string) $data['name']);
        $this->ensureUniqueCategoryName($companyId, $name);

        return ExpenseCategory::create([
            'company_id' => $companyId,
            'name' => $name,
            'description' => $data['description'] ?? null,
            'is_default' => false,
            'status' => $data['status'] ?? 'active',
        ]);
    }

    public function updateCategory(int $companyId, int $categoryId, array $data): ExpenseCategory
    {
        $category = ExpenseCategory::where('company_id', $companyId)
            ->where('is_default', false)
            ->findOrFail($categoryId);

        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            $this->ensureUniqueCategoryName($companyId, $name, $category->id);
            $category->name = $name;
        }

        if (array_key_exists('description', $data)) {
            $category->description = $data['description'];
        }

        if (isset($data['status'])) {
            $category->status = $data['status'];
        }

        $category->save();

        return $category->fresh();
    }

    public function deleteCategory(int $companyId, int $categoryId): void
    {
        $category = ExpenseCategory::where('company_id', $companyId)
            ->where('is_default', false)
            ->findOrFail($categoryId);

        $isUsed = Expense::withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->withTrashed()
            ->where('category_id', $category->id)
            ->exists();

        if ($isUsed) {
            throw ValidationException::withMessages([
                'category' => ['Cannot delete a category that is already used by expenses. You may disable it instead.'],
            ]);
        }

        $category->delete();
    }

    protected function ensureUniqueCategoryName(int $companyId, string $name, ?int $ignoreId = null): void
    {
        $query = ExpenseCategory::accessibleToCompany($companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)]);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => ['An expense category with this name already exists.'],
            ]);
        }
    }

    public function validateCategoryAccess(int $companyId, int $categoryId): ExpenseCategory
    {
        $this->ensureDefaultCategoriesSeeded();

        $category = ExpenseCategory::accessibleToCompany($companyId)
            ->where('status', 'active')
            ->find($categoryId);

        if (! $category) {
            throw ValidationException::withMessages([
                'category_id' => ['Selected expense category is invalid or disabled for this company.'],
            ]);
        }

        return $category;
    }

    public function createExpense(int $companyId, array $data, ?UploadedFile $attachment, ?User $user): Expense
    {
        $this->validateCategoryAccess($companyId, (int) $data['category_id']);

        $storedPath = null;
        $originalName = null;

        try {
            return DB::transaction(function () use ($companyId, $data, $attachment, $user, &$storedPath, &$originalName) {
                if ($attachment !== null) {
                    $storedPath = $attachment->store("expenses/company_{$companyId}", 'local');
                    $originalName = $attachment->getClientOriginalName();
                }

                $expense = Expense::withoutGlobalScope(\App\Scopes\CompanyScope::class)->create([
                    'company_id' => $companyId,
                    'category_id' => (int) $data['category_id'],
                    'amount' => round((float) $data['amount'], 2),
                    'expense_date' => $data['expense_date'],
                    'expense_time' => $data['expense_time'] ?? Carbon::now('Asia/Kolkata')->format('h:i A'),
                    'payment_mode' => $data['payment_mode'],
                    'paid_to' => $data['paid_to'] ?? null,
                    'description' => $data['description'] ?? null,
                    'attachment_path' => $storedPath,
                    'attachment_original_name' => $originalName,
                    'created_by' => $user?->id,
                    'updated_by' => $user?->id,
                ]);

                return $expense->load(['category', 'creator']);
            });
        } catch (\Throwable $e) {
            if ($storedPath && Storage::disk('local')->exists($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $e;
        }
    }

    public function updateExpense(Expense $expense, array $data, ?UploadedFile $attachment, bool $removeAttachment, ?User $user): Expense
    {
        if (isset($data['category_id'])) {
            $this->validateCategoryAccess((int) $expense->company_id, (int) $data['category_id']);
        }

        $oldPath = $expense->attachment_path;
        $newPath = null;

        try {
            $updated = DB::transaction(function () use ($expense, $data, $attachment, $removeAttachment, $user, $oldPath, &$newPath) {
                if ($attachment !== null) {
                    $newPath = $attachment->store("expenses/company_{$expense->company_id}", 'local');
                    $expense->attachment_path = $newPath;
                    $expense->attachment_original_name = $attachment->getClientOriginalName();
                } elseif ($removeAttachment) {
                    $expense->attachment_path = null;
                    $expense->attachment_original_name = null;
                }

                if (isset($data['category_id'])) {
                    $expense->category_id = (int) $data['category_id'];
                }
                if (isset($data['amount'])) {
                    $expense->amount = round((float) $data['amount'], 2);
                }
                if (isset($data['expense_date'])) {
                    $expense->expense_date = $data['expense_date'];
                }
                if (array_key_exists('expense_time', $data)) {
                    $expense->expense_time = $data['expense_time'];
                }
                if (isset($data['payment_mode'])) {
                    $expense->payment_mode = $data['payment_mode'];
                }
                if (array_key_exists('paid_to', $data)) {
                    $expense->paid_to = $data['paid_to'];
                }
                if (array_key_exists('description', $data)) {
                    $expense->description = $data['description'];
                }

                $expense->updated_by = $user?->id;
                $expense->save();

                return $expense->fresh(['category', 'creator']);
            });

            // Clean up old file safely after transaction commits
            if (($newPath !== null || $removeAttachment) && $oldPath && Storage::disk('local')->exists($oldPath)) {
                Storage::disk('local')->delete($oldPath);
            }

            return $updated;
        } catch (\Throwable $e) {
            if ($newPath && Storage::disk('local')->exists($newPath)) {
                Storage::disk('local')->delete($newPath);
            }
            throw $e;
        }
    }

    public function deleteExpense(Expense $expense, ?User $user): void
    {
        $expense->updated_by = $user?->id;
        $expense->save();
        $expense->delete();
    }

    /**
     * Aggregated summary metrics using database aggregation.
     */
    public function getSummary(int $companyId, ?string $fromDate = null, ?string $toDate = null, ?int $categoryId = null, ?string $paymentMode = null): array
    {
        $now = Carbon::now('Asia/Kolkata');
        $today = $now->toDateString();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        $baseQuery = Expense::withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->where('company_id', $companyId);

        $todayTotal = (float) (clone $baseQuery)
            ->whereDate('expense_date', $today)
            ->sum('amount');

        $monthTotal = (float) (clone $baseQuery)
            ->whereBetween('expense_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $filtered = clone $baseQuery;
        if ($fromDate && $toDate) {
            $filtered->whereBetween('expense_date', [$fromDate, $toDate]);
        } elseif ($fromDate) {
            $filtered->whereDate('expense_date', '>=', $fromDate);
        } elseif ($toDate) {
            $filtered->whereDate('expense_date', '<=', $toDate);
        }

        if ($categoryId) {
            $filtered->where('category_id', $categoryId);
        }
        if ($paymentMode) {
            $filtered->where('payment_mode', $paymentMode);
        }

        $agg = $filtered->selectRaw("
            COALESCE(SUM(amount), 0) as total_expenses,
            COUNT(*) as total_expense_count,
            COALESCE(SUM(CASE WHEN payment_mode = 'Cash' THEN amount ELSE 0 END), 0) as cash_expenses,
            COALESCE(SUM(CASE WHEN payment_mode = 'UPI' THEN amount ELSE 0 END), 0) as upi_expenses,
            COALESCE(SUM(CASE WHEN payment_mode = 'Bank' THEN amount ELSE 0 END), 0) as bank_expenses
        ")->first();

        return [
            'today_expenses' => round($todayTotal, 2),
            'month_expenses' => round($monthTotal, 2),
            'cash_expenses' => round((float) ($agg->cash_expenses ?? 0), 2),
            'upi_expenses' => round((float) ($agg->upi_expenses ?? 0), 2),
            'bank_expenses' => round((float) ($agg->bank_expenses ?? 0), 2),
            'total_expenses' => round((float) ($agg->total_expenses ?? 0), 2),
            'total_expense_count' => (int) ($agg->total_expense_count ?? 0),
        ];
    }

    /**
     * Detailed & Category-wise Expense Report using DB aggregation.
     */
    public function getReport(int $companyId, ?string $fromDate = null, ?string $toDate = null, ?int $categoryId = null, ?string $paymentMode = null): array
    {
        $summary = $this->getSummary($companyId, $fromDate, $toDate, $categoryId, $paymentMode);
        $grandTotal = $summary['total_expenses'];

        $catQuery = Expense::withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->join('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->where('expenses.company_id', $companyId)
            ->whereNull('expenses.deleted_at');

        if ($fromDate && $toDate) {
            $catQuery->whereBetween('expenses.expense_date', [$fromDate, $toDate]);
        } elseif ($fromDate) {
            $catQuery->whereDate('expenses.expense_date', '>=', $fromDate);
        } elseif ($toDate) {
            $catQuery->whereDate('expenses.expense_date', '<=', $toDate);
        }
        if ($categoryId) {
            $catQuery->where('expenses.category_id', $categoryId);
        }
        if ($paymentMode) {
            $catQuery->where('expenses.payment_mode', $paymentMode);
        }

        $categoryRows = $catQuery
            ->groupBy('expenses.category_id', 'expense_categories.name')
            ->selectRaw('expenses.category_id, expense_categories.name as category_name, COALESCE(SUM(expenses.amount), 0) as total_amount, COUNT(expenses.id) as entries_count')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($row) use ($grandTotal) {
                $amount = round((float) $row->total_amount, 2);
                $percentage = $grandTotal > 0 ? round(($amount / $grandTotal) * 100, 2) : 0.0;

                return [
                    'category_id' => (int) $row->category_id,
                    'category_name' => $row->category_name,
                    'total_amount' => $amount,
                    'entries_count' => (int) $row->entries_count,
                    'percentage' => $percentage,
                ];
            })
            ->values();

        $entriesQuery = Expense::withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->where('company_id', $companyId)
            ->with(['category', 'creator']);

        if ($fromDate && $toDate) {
            $entriesQuery->whereBetween('expense_date', [$fromDate, $toDate]);
        } elseif ($fromDate) {
            $entriesQuery->whereDate('expense_date', '>=', $fromDate);
        } elseif ($toDate) {
            $entriesQuery->whereDate('expense_date', '<=', $toDate);
        }
        if ($categoryId) {
            $entriesQuery->where('category_id', $categoryId);
        }
        if ($paymentMode) {
            $entriesQuery->where('payment_mode', $paymentMode);
        }

        $entries = $entriesQuery
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return [
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'category_id' => $categoryId,
                'payment_mode' => $paymentMode,
            ],
            'summary' => $summary,
            'category_summary' => $categoryRows,
            'entries' => $entries,
        ];
    }
}
