<?php

namespace App\Http\Requests\Dealer;

use Illuminate\Foundation\Http\FormRequest;

class DealerPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'custom_price' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Product selection is required.',
            'product_id.exists' => 'Selected product does not exist in the catalog.',
            'custom_price.required' => 'Custom price amount is required.',
            'custom_price.min' => 'Price cannot be negative.',
            'effective_from.required' => 'Effective start date is required.',
            'effective_to.after_or_equal' => 'Effective end date must be on or after the effective start date.',
        ];
    }
}
