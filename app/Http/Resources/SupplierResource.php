<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
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
            'opening_balance' => (float) $this->opening_balance,
            'current_balance' => $this->calculateOutstandingBalance(),
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,
            'purchases_count' => $this->whenLoaded('purchases', fn () => $this->purchases->count()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
