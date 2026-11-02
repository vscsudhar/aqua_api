<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rate' => (float) $this->rate,
            'tax_type' => $this->tax_type ?? 'gst',
            'applicability' => $this->applicability ?? 'all',
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_to' => $this->effective_to?->format('Y-m-d'),
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
            'description' => $this->description,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
