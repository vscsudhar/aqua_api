<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_company_id',
        'company_type',
        'company_code',
        'name',
        'contact_person',
        'logo_path',
        'plan_id',
        'expires_at',
        'status',
        'email',
        'phone',
        'address',
        'gstin',
    ];

    protected $hidden = [
        'logo_data',
    ];

    protected $casts = [
        'expires_at' => 'date',
    ];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        if (str_starts_with($this->logo_path, 'http://') || str_starts_with($this->logo_path, 'https://')) {
            return $this->logo_path;
        }

        $filename = basename($this->logo_path);
        return url("api/v1/public/logo-file/{$filename}");
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function adminUser(): HasOne
    {
        return $this->hasOne(User::class)->where('role', 'admin');
    }

    public function dealers(): HasMany
    {
        return $this->hasMany(Dealer::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(CompanySetting::class);
    }

    public function ownerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }

    public function childDealerCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_company_id');
    }

    /**
     * Scope for Main Subscription Companies created in aqua_user.
     */
    public function scopeMainCompanies($query)
    {
        return $query->whereNull('owner_company_id')
            ->whereNotNull('plan_id')
            ->where(function ($q) {
                $q->where('company_type', 'main')
                  ->orWhereNull('company_type');
            });
    }

    /**
     * Scope for Dealer / Corporate Customer Companies created in marina_aqua.
     */
    public function scopeDealerCompanies($query, $ownerCompanyId = null)
    {
        $q = $query->where(function ($sub) {
            $sub->where('company_type', 'dealer')
                ->orWhereNotNull('owner_company_id')
                ->orWhereNull('plan_id');
        });

        if ($ownerCompanyId) {
            $q->where('owner_company_id', $ownerCompanyId);
        } else {
            $q->whereNotNull('owner_company_id');
        }

        return $q;
    }

    public function isMainCompany(): bool
    {
        return empty($this->owner_company_id)
            && !empty($this->plan_id)
            && ($this->company_type === 'main' || empty($this->company_type));
    }

    public function isDealerCompany(): bool
    {
        return !empty($this->owner_company_id)
            || empty($this->plan_id)
            || $this->company_type === 'dealer';
    }
}