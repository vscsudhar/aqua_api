<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class EnsureCompanyPlanActiveMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super admins are never blocked by company subscription limits
        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        // Allow read-only (GET, HEAD, OPTIONS) and authentication/logout requests
        if ($request->isMethodSafe() || $request->is('*/logout') || $request->is('*/auth/me')) {
            return $next($request);
        }

        $company = $user->company;
        if (! $company) {
            return $next($request);
        }

        $isExpired = false;
        if ($company->status === 'inactive') {
            $isExpired = true;
        } elseif (! empty($company->expires_at)) {
            $expiresAt = Carbon::parse($company->expires_at)->endOfDay();
            if ($expiresAt->isPast()) {
                $isExpired = true;
            }
        }

        if ($isExpired) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your subscription plan has expired. Please renew your plan soon. You are currently in read-only mode to view existing data.',
                'is_plan_expired' => true,
                'expires_at' => $company->expires_at ? Carbon::parse($company->expires_at)->format('Y-m-d') : null,
            ], 403);
        }

        return $next($request);
    }
}

