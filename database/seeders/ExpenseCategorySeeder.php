<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public const DEFAULT_CATEGORIES = [
        'Fuel',
        'Vehicle Maintenance',
        'Staff Salary',
        'Rent',
        'Electricity',
        'Loading / Unloading',
        'Transport',
        'Food & Refreshments',
        'Packaging',
        'Office Expenses',
        'Repairs & Maintenance',
        'Others',
    ];

    /**
     * Seed global default expense categories idempotently.
     */
    public function run(): void
    {
        foreach (self::DEFAULT_CATEGORIES as $name) {
            ExpenseCategory::withTrashed()
                ->whereNull('company_id')
                ->where('name', $name)
                ->firstOr(function () use ($name) {
                    return ExpenseCategory::create([
                        'company_id' => null,
                        'name' => $name,
                        'is_default' => true,
                        'status' => 'active',
                    ]);
                });
        }
    }
}
