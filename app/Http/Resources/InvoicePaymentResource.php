<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoicePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'payment_no' => $this->payment_no,
            'payment_date' => $this->payment_date?->toDateString(),
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'reference_no' => $this->reference_no,
            'notes' => $this->notes,
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
