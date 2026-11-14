<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = request()->user() ?: Auth::user() ?: Auth::guard('sanctum')->user();

        // If not authenticated (e.g. console commands, unauthenticated requests), do not filter
        if (! $user) {
            return;
        }

        // Super Admin access: can filter by X-Company-Id or company_id if provided
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            $targetCompanyId = request()->header('X-Company-Id') ?: request()->input('company_id') ?: $user->company_id;
            if ($targetCompanyId) {
                $builder->where($model->getTable() . '.company_id', $targetCompanyId);
            }
            return;
        }

        // Non-super-admin tenant users
        $companyId = $user->company_id;
        if (! $companyId) {
            $builder->whereRaw('1 = 0');
            return;
        }

        // Validate client-supplied company_id: only allowed if owned/onboarded client company
        $requestedCompanyId = request()->header('X-Company-Id') ?: request()->input('company_id');
        if ($requestedCompanyId && (int)$requestedCompanyId !== (int)$companyId) {
            $isOwned = \App\Models\Company::withoutGlobalScopes()
                ->where('id', $requestedCompanyId)
                ->where('owner_company_id', $companyId)
                ->exists();

            if ($isOwned) {
                $builder->where($model->getTable() . '.company_id', $requestedCompanyId);
                return;
            }

            // Unauthorized cross-company attempt: block query results completely
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->getTable() . '.company_id', $companyId);
    }
}