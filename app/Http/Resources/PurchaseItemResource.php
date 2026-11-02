<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_id' => $this->purchase_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name ?? $this->product?->name,
            'product_sku' => $this->product_sku ?? $this->product?->sku,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_cost' => (float) $this->unit_cost,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'total_cost' => (float) $this->total_cost,
            'notes' => $this->notes,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
                'track_stock' => (bool) $this->product->track_stock,
                'current_stock' => (float) $this->product->current_stock,
            ]),
        ];
    }
}
