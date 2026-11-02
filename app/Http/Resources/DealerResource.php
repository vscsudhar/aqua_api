<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'gstin' => $this->gstin,
            'credit_limit' => (float) $this->credit_limit,
            'opening_balance' => (float) $this->opening_balance,
            'opening_date' => $this->opening_date?->toDateString(),
            'current_outstanding' => $this->calculateOutstandingBalance(),
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'is_referenced' => $this->isReferencedInTransactions(),
            'supplies_count' => $this->whenCounted('supplies'),
            'payments_count' => $this->whenCounted('payments'),
            'product_prices_count' => $this->whenCounted('productPrices'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
