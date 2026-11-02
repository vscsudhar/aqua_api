<?php

namespace App\Traits;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToCompany
{
    /**
     * Boot the BelongsToCompany trait.
     */
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $user = request()->user() ?: Auth::user() ?: Auth::guard('sanctum')->user();
            if ($user && method_exists($user, 'isSuperAdmin') && !$user->isSuperAdmin()) {
                // Always enforce authenticated user's company_id. Never trust client payload.
                $model->company_id = $user->company_id;
            } elseif (empty($model->company_id) && $user && !empty($user->company_id)) {
                $model->company_id = $user->company_id;
            }
        });
    }

    /**
     * Relationship to the owning company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}