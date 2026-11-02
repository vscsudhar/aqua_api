<?php

namespace App\Services;

use App\Models\Dealer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Supply;
use App\Models\SupplyItem;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Get real-time dashboard summary metrics without maintaining duplicate tables.
     */
    public function getSummary(?string $targetDate = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $timezone = 'Asia/Kolkata';
        $today = $targetDate ? Carbon::parse($targetDate, $timezone)->format('Y-m-d') : Carbon::today($timezone)->format('Y-m-d');

        // 1. Dealer & Product counts
        $totalActiveDealers = Dealer::where('is_active', true)->count();
        $totalActiveProducts = Product::where('is_active', true)->count();

        // 2. Supplies for the target date or date range
        $suppliesQuery = Supply::where('status', 'delivered');
        if ($startDate && $endDate) {
            $suppliesQuery->whereBetween('supply_date', [$startDate, $endDate]);
        } else {
            $suppliesQuery->whereDate('supply_date', $today);
        }

        $todaySupplyAmount = (float) $suppliesQuery->sum('total_amount');

        // Today's supply quantity from supply_items
        $supplyIds = (clone $suppliesQuery)->pluck('id');
        $todaySupplyQuantity = (float) SupplyItem::whereIn('supply_id', $supplyIds)->sum('quantity');

        // 3. Payments collected for the target date or date range
        $paymentsQuery = Payment::where('status', 'completed');
        if ($startDate && $endDate) {
            $paymentsQuery->whereBetween('payment_date', [$startDate, $endDate]);
        } else {
            $paymentsQuery->whereDate('payment_date', $today);
        }

        $todayCollectedAmount = (float) $paymentsQuery->sum('amount');

        // 4. Total outstanding dealer balance across all active dealers
        // Exactly matches ledger formula: Opening Balance + Delivered Supplies - Completed Payments
        $totalOpeningBalance = (float) Dealer::where('is_active', true)->sum('opening_balance');
        $totalDeliveredSupplies = (float) Supply::where('status', 'delivered')
            ->whereHas('dealer', fn ($q) => $q->where('is_active', true))
            ->sum('total_amount');
        $totalCompletedPayments = (float) Payment::where('status', 'completed')
            ->whereHas('dealer', fn ($q) => $q->where('is_active', true))
            ->sum('amount');

        $totalOutstandingBalance = round($totalOpeningBalance + $totalDeliveredSupplies - $totalCompletedPayments, 2);

        // 5. Low-stock products
        $lowStockProducts = Product::where('is_active', true)
            ->whereColumn('current_stock', '<=', 'min_stock_level')
            ->with('category:id,name,code')
            ->orderBy('current_stock', 'asc')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'unit' => $p->unit,
                'current_stock' => (float) $p->current_stock,
                'min_stock_level' => (float) $p->min_stock_level,
                'category_name' => $p->category?->name ?? 'Uncategorized',
            ]);

        // 6. Recent transactions (Last 10 supplies and collections combined)
        $recentSupplies = Supply::with('dealer:id,name,code')
            ->orderBy('supply_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'type' => 'supply',
                'type_label' => 'Water Supply Delivery',
                'reference_no' => $s->supply_no,
                'dealer_name' => $s->dealer?->name ?? 'Unknown Dealer',
                'dealer_code' => $s->dealer?->code ?? '',
                'amount' => (float) $s->total_amount,
                'date' => $s->supply_date?->format('Y-m-d'),
                'status' => $s->status,
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        $recentPayments = Payment::with('dealer:id,name,code')
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'type' => 'payment',
                'type_label' => 'Payment Collection ('.strtoupper($p->payment_mode).')',
                'reference_no' => $p->payment_no,
                'dealer_name' => $p->dealer?->name ?? 'Unknown Dealer',
                'dealer_code' => $p->dealer?->code ?? '',
                'amount' => (float) $p->amount,
                'date' => $p->payment_date?->format('Y-m-d'),
                'status' => $p->status,
                'created_at' => $p->created_at?->toIso8601String(),
            ]);

        $recentTransactions = $recentSupplies->concat($recentPayments)
            ->sortByDesc('created_at')
            ->values()
            ->take(10);

        return [
            'metrics' => [
                'total_active_dealers' => $totalActiveDealers,
                'total_active_products' => $totalActiveProducts,
                'today_supply_quantity' => $todaySupplyQuantity,
                'today_supply_amount' => $todaySupplyAmount,
                'today_collected_amount' => $todayCollectedAmount,
                'total_outstanding_dealer_balance' => $totalOutstandingBalance,
                'low_stock_items_count' => $lowStockProducts->count(),
                'date' => $today,
                'timezone' => $timezone,
                'currency' => 'INR',
            ],
            'low_stock_items' => $lowStockProducts,
            'recent_transactions' => $recentTransactions,
        ];
    }

    /**
     * Get chart trends for collections and monthly sales.
     */
    public function getCharts(int $days = 14, int $months = 6): array
    {
        $timezone = 'Asia/Kolkata';
        $today = Carbon::today($timezone);

        // 1. Daily collections over the last N days
        $startDate = (clone $today)->subDays($days - 1);
        $dailyCollectionsRaw = Payment::where('status', 'completed')
            ->whereDate('payment_date', '>=', $startDate->format('Y-m-d'))
            ->whereDate('payment_date', '<=', $today->format('Y-m-d'))
            ->selectRaw('DATE(payment_date) as p_date, SUM(amount) as total_amount, COUNT(id) as count')
            ->groupBy('p_date')
            ->orderBy('p_date', 'asc')
            ->get()
            ->keyBy('p_date');

        $dailyCollections = [];
        for ($i = 0; $i < $days; $i++) {
            $curDate = (clone $startDate)->addDays($i);
            $dateStr = $curDate->format('Y-m-d');
            $dayLabel = $curDate->format('d M');

            $dailyCollections[] = [
                'date' => $dateStr,
                'label' => $dayLabel,
                'day' => $curDate->format('D'),
                'amount' => isset($dailyCollectionsRaw[$dateStr]) ? (float) $dailyCollectionsRaw[$dateStr]->total_amount : 0.0,
                'count' => isset($dailyCollectionsRaw[$dateStr]) ? (int) $dailyCollectionsRaw[$dateStr]->count : 0,
            ];
        }

        // 2. Monthly sales over the last N months
        $monthlySales = [];
        for ($m = $months - 1; $m >= 0; $m--) {
            $monthDate = (clone $today)->subMonths($m);
            $year = $monthDate->year;
            $month = $monthDate->month;
            $monthLabel = $monthDate->format('M Y');

            $supplies = Supply::where('status', 'delivered')
                ->whereYear('supply_date', $year)
                ->whereMonth('supply_date', $month);

            $amount = (float) $supplies->sum('total_amount');
            $supplyIds = (clone $supplies)->pluck('id');
            $quantity = (float) SupplyItem::whereIn('supply_id', $supplyIds)->sum('quantity');

            $monthlySales[] = [
                'year' => $year,
                'month' => $month,
                'label' => $monthLabel,
                'amount' => $amount,
                'quantity' => $quantity,
            ];
        }

        // 3. Payment modes breakdown
        $paymentModes = Payment::where('status', 'completed')
            ->selectRaw('payment_mode, SUM(amount) as total_amount, COUNT(id) as count')
            ->groupBy('payment_mode')
            ->get()
            ->map(fn ($pm) => [
                'mode' => $pm->payment_mode,
                'mode_label' => strtoupper($pm->payment_mode),
                'amount' => (float) $pm->total_amount,
                'count' => (int) $pm->count,
            ]);

        return [
            'daily_collections' => $dailyCollections,
            'monthly_sales' => $monthlySales,
            'payment_modes' => $paymentModes,
        ];
    }
}
