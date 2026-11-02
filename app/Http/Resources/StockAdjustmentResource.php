<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adjustment_no' => $this->adjustment_no,
            'adjustment_date' => $this->adjustment_date?->toDateString(),
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'product_sku' => $this->product?->sku,
            'adjustment_type' => $this->adjustment_type,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'cost_price' => (float) $this->cost_price,
            'stock_before' => (float) $this->stock_before,
            'stock_after' => (float) $this->stock_after,
            'reason' => $this->reason,
            'authorized_by' => $this->authorized_by,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
