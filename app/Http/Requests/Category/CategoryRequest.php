<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category') ?? $this->route('id');
        if ($categoryId instanceof \App\Models\ProductCategory) {
            $categoryId = $categoryId->id;
        }

        $user = $this->user();
        $companyId = $user?->company_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('product_categories', 'code')
                    ->where(function ($query) use ($companyId) {
                        return $companyId ? $query->where('company_id', $companyId) : $query;
                    })
                    ->ignore($categoryId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
