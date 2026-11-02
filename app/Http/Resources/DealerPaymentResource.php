<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealerPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_no' => $this->payment_no,
            'idempotency_key' => $this->idempotency_key,
            'dealer_id' => $this->dealer_id,
            'dealer_name' => $this->dealer?->name,
            'dealer_code' => $this->dealer?->code,
            'dealer_supply_id' => $this->dealer_supply_id,
            'supply_no' => $this->supply?->supply_no,
            'payment_date' => $this->payment_date?->toDateString(),
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method ?? $this->payment_mode ?? 'cash',
            'reference_no' => $this->reference_no,
            'status' => $this->status,
            'notes' => $this->notes ?? $this->remarks,
            'collected_by_name' => $this->collector?->name ?? $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
