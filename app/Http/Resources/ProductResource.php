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
            'category_id' => $this->category_id,
            'unit_id' => $this->unit_id,
            'unit_name' => $this->unitRel?->name ?? $this->unit ?? 'Unit',
            'unit_code' => $this->unitRel?->code ?? '',
            'category_name' => $this->category?->name ?? 'Uncategorized',
            'description' => $this->description,
            'hsn_code' => $this->hsn_code ?? '2201',
            'default_price' => (float) $this->default_price,
            'cost_price' => (float) $this->cost_price,
            'tax_rate' => (float) $this->tax_rate,
            'current_stock' => (float) $this->current_stock,
            'track_stock' => (bool) $this->track_stock,
            'min_stock_level' => (float) $this->min_stock_level,
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
