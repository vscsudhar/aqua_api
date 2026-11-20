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
        $logoPath = $this->logo_path ?: $this->settings?->logo_path;
        if (! $logoPath) {
            return null;
        }

        if (str_starts_with($logoPath, 'http://') || str_starts_with($logoPath, 'https://')) {
            return preg_replace('/^http:\/\/aqua-api\.ethiodeploy\.app/i', 'https://aqua-api.ethiodeploy.app', $logoPath);
        }

        $filename = basename($logoPath);
        $disk = Storage::disk('public');

        // Verify file physically exists on disk
        if (! $disk->exists("logos/{$filename}")) {
            // Self-heal: check if another valid logo file exists on disk for this company
            $companyId = $this->id;
            $files = $disk->files('logos');
            $matching = array_filter($files, function ($file) use ($companyId) {
                return preg_match('/^logos\/company_logo_'.$companyId.'_\d+\./', $file);
            });

            if (! empty($matching)) {
                rsort($matching);
                $filename = basename($matching[0]);
                $this->updateQuietly(['logo_path' => "logos/{$filename}"]);
            } else {
                return null;
            }
        }

        $url = url("api/v1/public/logo-file/{$filename}");
        if (str_starts_with(config('app.url'), 'https://')
            || request()?->isSecure()
            || request()?->header('X-Forwarded-Proto') === 'https'
            || str_contains($url, 'ethiodeploy.app')
            || ! app()->environment('local')) {
            $url = preg_replace('/^http:\/\//i', 'https://', $url);
        }

        return $url;
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