<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DamagedProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'damage_no' => $this->damage_no,
            'damage_date' => $this->damage_date?->toDateString(),
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'variant' => $this->variant,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => (float) $this->total_cost,
            'stock_before' => (float) $this->stock_before,
            'stock_after' => (float) $this->stock_after,
            'reason' => $this->reason,
            'remarks' => $this->remarks,
            'created_by' => $this->creator?->name,
            'updated_by' => $this->updater?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
                'unit' => $this->product->unit,
                'current_stock' => (float) $this->product->current_stock,
            ]),
        ];
    }
}
