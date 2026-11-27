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

        // Super Admin access: unrestricted across companies (no header-based context switching)
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return;
        }

        // Tenant users: strictly scoped to their authenticated company_id
        $companyId = $user->company_id;
        if (! $companyId) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->getTable() . '.company_id', $companyId);
    }
}