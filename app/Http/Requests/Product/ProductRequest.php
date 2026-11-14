<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product') ?? $this->route('id');
        $isRefill = $this->input('product_type') === 'refill';

        $user = $this->user();
        $companyId = $user?->company_id;
        if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            $companyId = $this->header('X-Company-Id') ?: $this->input('company_id') ?: $user->company_id;
        } elseif ($user && $user->company_id) {
            $requested = $this->header('X-Company-Id') ?: $this->input('company_id');
            if ($requested && (int) $requested !== (int) $user->company_id) {
                $isOwned = \App\Models\Company::withoutGlobalScopes()
                    ->where('id', $requested)
                    ->where('owner_company_id', $user->company_id)
                    ->exists();
                if ($isOwned) {
                    $companyId = (int) $requested;
                }
            }
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'sku')
                    ->where(fn ($query) => $companyId ? $query->where('company_id', $companyId) : $query)
                    ->whereNull('deleted_at')
                    ->ignore($productId),
            ],
            'product_type' => ['nullable', 'string', 'in:normal,refill'],
            'category_id' => ['required', 'exists:product_categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => $isRefill ? ['nullable', 'numeric'] : ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'current_stock' => $isRefill ? ['nullable', 'numeric'] : ['nullable', 'numeric', 'min:0'],
            'min_stock_level' => $isRefill ? ['nullable', 'numeric'] : ['nullable', 'numeric', 'min:0'],
            'track_stock' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
