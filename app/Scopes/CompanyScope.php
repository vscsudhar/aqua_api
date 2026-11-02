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

        // If authenticated and is not a super_admin
        if (method_exists($user, 'isSuperAdmin') && ! $user->isSuperAdmin()) {
            if ($user->company_id) {
                $builder->where($model->getTable() . '.company_id', $user->company_id);
            } else {
                $builder->whereRaw('1 = 0');
            }
        }
    }
}