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
            if (! $user) {
                return;
            }

            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                $targetId = request()->header('X-Company-Id') ?: request()->input('company_id') ?: $model->company_id ?: $user->company_id;
                if ($targetId) {
                    $model->company_id = $targetId;
                }
                return;
            }

            // Tenant user
            $companyId = $user->company_id;
            $requestedCompanyId = request()->header('X-Company-Id') ?: request()->input('company_id');
            if ($requestedCompanyId && (int)$requestedCompanyId !== (int)$companyId) {
                $isOwned = Company::withoutGlobalScopes()
                    ->where('id', $requestedCompanyId)
                    ->where('owner_company_id', $companyId)
                    ->exists();

                if ($isOwned) {
                    $model->company_id = $requestedCompanyId;
                    return;
                }
            }

            // Always enforce authorized company_id
            $model->company_id = $companyId;
        });

        static::updating(function ($model) {
            // Prevent unauthorized cross-company mutation of company_id
            if ($model->isDirty('company_id') && !empty($model->getOriginal('company_id'))) {
                $user = request()->user() ?: Auth::user() ?: Auth::guard('sanctum')->user();
                if (!$user || !method_exists($user, 'isSuperAdmin') || !$user->isSuperAdmin()) {
                    $model->company_id = $model->getOriginal('company_id');
                }
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