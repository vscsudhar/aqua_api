<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Retrieve aggregated real-time dashboard KPIs, low stock alerts, and recent transactions.
     */
    public function summary(Request $request): JsonResponse
    {
        $targetDate = $request->query('date');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $data = $this->dashboardService->getSummary($targetDate, $startDate, $endDate);

        return $this->successResponse($data, 'Dashboard summary metrics retrieved successfully.');
    }

    /**
     * Retrieve collection and sales trend data for charts.
     */
    public function charts(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 14);
        $months = (int) $request->query('months', 6);

        if ($days < 3 || $days > 60) {
            $days = 14;
        }

        if ($months < 1 || $months > 24) {
            $months = 6;
        }

        $data = $this->dashboardService->getCharts($days, $months);

        return $this->successResponse($data, 'Dashboard chart metrics retrieved successfully.');
    }
}
