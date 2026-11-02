<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_no' => $this->purchase_no,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier_name,
            'invoice_no' => $this->invoice_no,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'notes' => $this->notes,
            'supplier' => $this->whenLoaded('supplier', fn () => new SupplierResource($this->supplier)),
            'items' => PurchaseItemResource::collection($this->whenLoaded('items')),
            'movements' => $this->whenLoaded('movements', fn () => InventoryMovementResource::collection($this->movements)),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
