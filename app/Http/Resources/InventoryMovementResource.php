<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movement_no' => $this->movement_no,
            'movement_date' => $this->movement_date?->toDateString(),
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'product_sku' => $this->product?->sku,
            'movement_type' => $this->movement_type,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference_no' => $this->reference_no,
            'quantity' => (float) $this->quantity,
            'signed_quantity' => (float) $this->signed_quantity,
            'unit' => $this->unit,
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => (float) $this->total_cost,
            'balance_before' => (float) $this->balance_before,
            'balance_after' => (float) $this->balance_after,
            'notes' => $this->notes,
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
