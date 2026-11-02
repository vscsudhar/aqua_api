<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'product_sku' => $this->product?->sku,
            'item_description' => $this->item_description,
            'hsn_sac' => $this->hsn_sac,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => (float) $this->unit_price,
            'discount_amount' => (float) $this->discount_amount,
            'taxable_amount' => (float) $this->taxable_amount,
            'tax_rate' => (float) $this->tax_rate,
            'cgst_rate' => (float) $this->cgst_rate,
            'cgst_amount' => (float) $this->cgst_amount,
            'sgst_rate' => (float) $this->sgst_rate,
            'sgst_amount' => (float) $this->sgst_amount,
            'igst_rate' => (float) $this->igst_rate,
            'igst_amount' => (float) $this->igst_amount,
            'line_total' => (float) $this->line_total,
            'notes' => $this->notes,
        ];
    }
}
