<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'dealer_id' => $this->dealer_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'customer_address' => $this->customer_address,
            'customer_gstin' => $this->customer_gstin,
            'place_of_supply' => $this->place_of_supply,
            'is_interstate' => (bool) $this->is_interstate,
            'dealer_supply_id' => $this->dealer_supply_id,
            'dealer_supply_no' => $this->supply?->supply_no,
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'taxable_amount' => (float) $this->taxable_amount,
            'cgst_amount' => (float) $this->cgst_amount,
            'sgst_amount' => (float) $this->sgst_amount,
            'igst_amount' => (float) $this->igst_amount,
            'tax_total' => (float) $this->tax_total,
            'round_off' => (float) $this->round_off,
            'grand_total' => (float) $this->grand_total,
            'paid_amount' => (float) $this->paid_amount,
            'balance_due' => (float) $this->balance_due,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->canceller?->name,
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'finalized_by' => $this->finalizer?->name,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'items_count' => $this->whenCounted('items'),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => InvoicePaymentResource::collection($this->whenLoaded('payments')),
            'credit_notes' => CreditNoteResource::collection($this->whenLoaded('creditNotes')),
            'dealer' => $this->whenLoaded('dealer', fn () => [
                'id' => $this->dealer->id,
                'name' => $this->dealer->name,
                'code' => $this->dealer->code,
                'phone' => $this->dealer->phone,
                'gstin' => $this->dealer->gstin,
            ]),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
