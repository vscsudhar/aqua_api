<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'product_type' => $this->product_type ?? 'normal',
            'category_id' => $this->category_id,
            'unit_id' => $this->unit_id,
            'unit_name' => $this->unitRel?->name ?? $this->unit ?? 'Unit',
            'unit_code' => $this->unitRel?->code ?? '',
            'category_name' => $this->category?->name ?? 'Uncategorized',
            'description' => $this->description,
            'hsn_code' => $this->hsn_code ?? '2201',
            'default_price' => (float) $this->default_price,
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : null,
            'tax_rate' => (float) $this->tax_rate,
            'current_stock' => $this->current_stock !== null ? (float) $this->current_stock : null,
            'track_stock' => (bool) $this->track_stock,
            'min_stock_level' => $this->min_stock_level !== null ? (float) $this->min_stock_level : null,
            'is_low_stock' => $this->isLowStock(),
            'is_active' => (bool) $this->is_active,
            'is_referenced' => $this->isReferencedInTransactions(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'unit' => new UnitResource($this->whenLoaded('unitRel')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
