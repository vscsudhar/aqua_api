<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CompanySetting extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'company_name',
        'tagline',
        'phone',
        'company_contact_number',
        'email',
        'website',
        'address',
        'state_name',
        'state_code',
        'gstin',
        'pan',
        'fssai_no',
        'bank_name',
        'bank_branch',
        'account_number',
        'ifsc_code',
        'upi_id',
        'currency_symbol',
        'currency_code',
        'default_tax_rate',
        'logo_path',
        'invoice_prefix',
        'invoice_number_padding',
        'next_invoice_number',
        'invoice_footer_notes',
    ];

    protected $hidden = [
        'logo_data',
    ];

    protected $casts = [
        'default_tax_rate' => 'decimal:2',
        'invoice_number_padding' => 'integer',
        'next_invoice_number' => 'integer',
    ];

    protected $appends = ['logo_url', 'company_contact_number'];

    public function getCompanyContactNumberAttribute(): ?string
    {
        return $this->phone ?: null;
    }

    public function setCompanyContactNumberAttribute($value): void
    {
        $this->attributes['phone'] = $value ?: '';
    }

    public function getLogoUrlAttribute(): ?string
    {
        $logoPath = $this->logo_path ?: \App\Models\Company::where('id', $this->company_id)->value('logo_path');
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
            $companyId = $this->company_id;
            if ($companyId) {
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

    /**
     * Get or create the company setting instance for the active company.
     */
    public static function getSettings(?int $companyId = null): self
    {
        $targetCompanyId = $companyId ?: (Auth::user()?->company_id ?: 1);
        $company = Company::find($targetCompanyId);
        $name = $company?->name ?? 'Marina Aqua Pure Water Co.';

        $setting = self::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $targetCompanyId],
            [
                'company_name' => $name,
                'tagline' => 'Pure & Mineral Rich Packaged Drinking Water',
                'phone' => $company?->phone ?? '',
                'email' => $company?->email ?? 'billing@marinaaqua.com',
                'website' => 'www.marinaaqua.com',
                'address' => $company?->address ?? 'Plot No. 14, OMR IT Highway, Thoraipakkam, Chennai - 600097',
                'state_name' => 'Tamil Nadu',
                'state_code' => '33',
                'gstin' => '33AABCM1234F1Z9',
                'pan' => 'AABCM1234F',
                'bank_name' => 'State Bank of India',
                'bank_branch' => 'Thoraipakkam, Chennai',
                'account_number' => '38992010045',
                'ifsc_code' => 'SBIN0004567',
                'upi_id' => 'marinaaqua@sbi',
                'currency_symbol' => '₹',
                'currency_code' => 'INR',
                'default_tax_rate' => 18.00,
                'invoice_prefix' => 'INV',
                'invoice_number_padding' => 4,
                'next_invoice_number' => 1,
                'invoice_footer_notes' => "1. Goods once sold will not be taken back.\n2. Payment terms: 15 days.\n3. Subject to Chennai jurisdiction.",
            ]
        );

        // Keep company_name strictly in sync with the official company name created in Aqua User
        if ($company && !empty($company->name) && $setting->company_name !== $company->name) {
            $setting->update(['company_name' => $company->name]);
            $setting->company_name = $company->name;
        }

        // Keep logo_path in sync between company and company_settings
        if ($company && !empty($company->logo_path) && empty($setting->logo_path)) {
            $setting->update([
                'logo_path' => $company->logo_path,
            ]);
            $setting->logo_path = $company->logo_path;
        } elseif ($company && !empty($setting->logo_path) && empty($company->logo_path)) {
            $company->update([
                'logo_path' => $setting->logo_path,
            ]);
        }

        return $setting;
    }
}