<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealerSupplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supply_no' => $this->supply_no,
            'idempotency_key' => $this->idempotency_key,
            'dealer_id' => $this->dealer_id,
            'dealer_name' => $this->dealer?->name,
            'dealer_code' => $this->dealer?->code,
            'dealer_phone' => $this->dealer?->phone,
            'supply_date' => $this->supply_date?->toDateString(),
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'adjustment_amount' => (float) $this->adjustment_amount,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'outstanding_amount' => (float) $this->outstanding_amount,
            'payment_status' => $this->payment_status,
            'status' => $this->status,
            'notes' => $this->notes,
            'creator_name' => $this->creator?->name,
            'updater_name' => $this->updater?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'items' => DealerSupplyItemResource::collection($this->whenLoaded('items')),
            'payments' => DealerPaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
