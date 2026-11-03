<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanySettingResource;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanySettingController extends Controller
{
    /**
     * Get company profile & invoice settings.
     */
    public function show(Request $request): JsonResponse
    {
        $settings = CompanySetting::getSettings($request->user()?->company_id);

        return response()->json([
            'status' => 'success',
            'message' => 'Company settings retrieved successfully.',
            'data' => new CompanySettingResource($settings),
        ]);
    }

    /**
     * Update company settings.
     */
    public function update(Request $request): JsonResponse
    {
        $settings = CompanySetting::getSettings($request->user()?->company_id);

        $validated = $request->validate([
            'company_name' => ['sometimes', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:50'],
            'email' => ['sometimes', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'state_name' => ['sometimes', 'string', 'max:100'],
            'state_code' => ['sometimes', 'string', 'max:10'],
            'gstin' => ['nullable', 'string', 'max:50'],
            'pan' => ['nullable', 'string', 'max:50'],
            'fssai_no' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'ifsc_code' => ['nullable', 'string', 'max:50'],
            'upi_id' => ['nullable', 'string', 'max:100'],
            'currency_symbol' => ['sometimes', 'string', 'max:10'],
            'currency_code' => ['sometimes', 'string', 'max:10'],
            'default_tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'invoice_prefix' => ['sometimes', 'nullable', 'string', 'max:20'],
            'invoice_number_padding' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'next_invoice_number' => ['sometimes', 'integer', 'min:1'],
            'invoice_footer_notes' => ['nullable', 'string'],
        ]);

        // Always preserve authoritative company name from company created in aqua user
        $user = $request->user();
        if ($user && $user->company_id) {
            $comp = \App\Models\Company::find($user->company_id);
            if ($comp && !empty($comp->name)) {
                $validated['company_name'] = $comp->name;
            }
        }

        $settings->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Company settings updated successfully.',
            'data' => new CompanySettingResource($settings),
        ]);
    }

    /**
     * Upload and configure company logo.
     * Enforces file type and size limits (max 2MB, jpeg/png/webp/svg).
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,png,jpg,webp,svg',
                'max:2048', // 2MB max
            ],
        ]);

        $settings = CompanySetting::getSettings($request->user()?->company_id);

        // Remove old custom logo from storage if exists
        if ($settings->logo_path && ! str_starts_with($settings->logo_path, 'http')) {
            if (Storage::disk('public')->exists($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }
        }

        // Store new file
        $file = $request->file('logo');
        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'company_logo_'.($settings->company_id ?: 1).'_'.time().'.'.$extension;
        $path = $file->storeAs('logos', $filename, 'public');

        $settings->update(['logo_path' => $path]);

        if (! empty($settings->company_id)) {
            \App\Models\Company::where('id', $settings->company_id)->update(['logo_path' => $path]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Company logo uploaded successfully.',
            'data' => new CompanySettingResource($settings),
        ]);
    }

    /**
     * Remove custom company logo.
     */
    public function deleteLogo(Request $request): JsonResponse
    {
        $settings = CompanySetting::getSettings($request->user()?->company_id);

        if ($settings->logo_path && ! str_starts_with($settings->logo_path, 'http')) {
            if (Storage::disk('public')->exists($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }
        }

        $settings->update(['logo_path' => null]);

        if (! empty($settings->company_id)) {
            \App\Models\Company::where('id', $settings->company_id)->update(['logo_path' => null]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Company logo removed successfully.',
            'data' => new CompanySettingResource($settings),
        ]);
    }

    /**
     * Direct image stream for logo.
     */
    public function streamLogo()
    {
        $settings = CompanySetting::getSettings();

        if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
            $filePath = Storage::disk('public')->path($settings->logo_path);
            $mime = @mime_content_type($filePath) ?: 'image/png';

            return response()->file($filePath, [
                'Content-Type' => $mime,
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            ]);
        }

        // Return clean SVG badge placeholder if no custom logo uploaded
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="60" viewBox="0 0 200 60">'
            .'<rect width="200" height="60" rx="8" fill="#0284C7"/>'
            .'<circle cx="30" cy="30" r="14" fill="#ffffff" fill-opacity="0.2"/>'
            .'<path d="M30 18 C26 23 23 27 23 31 C23 35 26 38 30 38 C34 38 37 35 37 31 C37 27 34 23 30 18 Z" fill="#ffffff"/>'
            .'<text x="56" y="28" font-family="Arial, Helvetica, sans-serif" font-weight="bold" font-size="14" fill="#ffffff">MARINA AQUA</text>'
            .'<text x="56" y="44" font-family="Arial, Helvetica, sans-serif" font-size="10" fill="#E0F2FE">PURE PACKAGED WATER</text>'
            .'</svg>';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Public direct stream for company logo by company ID with CORS headers.
     */
    public function streamPublicLogo($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $logoPath = $company?->logo_path;

        if (! $logoPath) {
            $setting = CompanySetting::withoutGlobalScopes()->where('company_id', $companyId)->first();
            $logoPath = $setting?->logo_path;
        }

        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            $filePath = Storage::disk('public')->path($logoPath);
            $mime = @mime_content_type($filePath) ?: 'image/png';

            return response()->file($filePath, [
                'Content-Type' => $mime,
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
                'Cache-Control' => 'no-cache, private, must-revalidate',
            ]);
        }

        return response()->noContent(404, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Public direct stream for logo file by filename with CORS headers.
     */
    public function streamLogoFile($filename)
    {
        $path = "logos/{$filename}";
        if (Storage::disk('public')->exists($path)) {
            $filePath = Storage::disk('public')->path($path);
            $mime = @mime_content_type($filePath) ?: 'image/png';

            return response()->file($filePath, [
                'Content-Type' => $mime,
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
                'Cache-Control' => 'no-cache, private, must-revalidate',
            ]);
        }

        return response()->noContent(404, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
