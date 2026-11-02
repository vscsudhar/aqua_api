<?php

namespace App\Http\Requests\Supply;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDealerSupplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supply_date' => ['nullable', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'adjustment_amount' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.custom_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
