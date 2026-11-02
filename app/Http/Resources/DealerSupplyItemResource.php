<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealerSupplyItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'product_sku' => $this->product?->sku,
            'unit' => $this->unit ?? $this->product?->unit,
            'quantity' => (float) $this->quantity,
            'catalog_price' => (float) $this->catalog_price,
            'applied_price' => (float) $this->applied_price,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'line_total' => (float) $this->line_total,
            'notes' => $this->notes,
        ];
    }
}
