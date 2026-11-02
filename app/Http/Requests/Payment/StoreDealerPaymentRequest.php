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
            'payment_method' => ['required', 'in:cash,cheque,upi,bank_transfer,neft'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
