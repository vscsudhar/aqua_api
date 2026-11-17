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

        // Allow authentication, me, logout, and backup check
        if ($request->is('*/logout') || $request->is('*/auth/me') || $request->is('*/backup-check')) {
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
            $expiresAt = Carbon::parse($company->expires_at, 'Asia/Kolkata')->endOfDay();
            if ($expiresAt->isPast()) {
                $isExpired = true;
            }
        }

        if ($isExpired) {
            // Check if this is a blocked operation:
            // 1) All non-safe HTTP methods (POST, PUT, PATCH, DELETE)
            // 2) Export/download/print/generate endpoints even on GET
            $path = $request->path();
            $isExportOrPrint = (bool) preg_match('#/(pdf|print|html|export-pdf|export-excel|export-csv)$#i', $path);

            if (! $request->isMethodSafe() || $isExportOrPrint) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => 'SUBSCRIPTION_EXPIRED',
                    'message' => 'Your plan has expired. Please renew your subscription. Contact SS Aqua Flow.',
                    'is_plan_expired' => true,
                    'expires_at' => $company->expires_at ? Carbon::parse($company->expires_at)->format('Y-m-d') : null,
                ], 403);
            }
        }

        return $next($request);
    }
}

