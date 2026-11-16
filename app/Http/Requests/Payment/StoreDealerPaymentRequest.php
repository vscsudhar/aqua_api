<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StoreDealerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dealer_id' => ['required', 'exists:dealers,id'],
            'dealer_supply_id' => ['nullable', 'exists:dealer_supplies,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'include_in_ledger_amount' => ['nullable', 'boolean'],
            'payment_splits' => ['nullable', 'array'],
            'payment_splits.*.payment_mode' => ['required_with:payment_splits', 'string'],
            'payment_splits.*.amount' => ['required_with:payment_splits', 'numeric', 'gt:0'],
            'payment_splits.*.reference_no' => ['nullable', 'string', 'max:100'],
            'payment_splits.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
