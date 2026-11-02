<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealerPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dealer_id' => $this->dealer_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name ?? 'Unknown Product',
            'product_sku' => $this->product?->sku ?? '',
            'unit_name' => $this->product?->unitRel?->name ?? $this->product?->unit ?? 'Unit',
            'unit_code' => $this->product?->unitRel?->code ?? '',
            'category_name' => $this->product?->category?->name ?? 'General',
            'catalog_price' => $this->product ? (float) $this->product->default_price : 0.0,
            'custom_price' => (float) $this->custom_price,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_active' => (bool) $this->is_active,
            'is_currently_effective' => $this->isCurrentlyEffective(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
