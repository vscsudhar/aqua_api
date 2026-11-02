<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'credit_note_no' => $this->credit_note_no,
            'credit_note_date' => $this->credit_note_date?->toDateString(),
            'invoice_id' => $this->invoice_id,
            'dealer_id' => $this->dealer_id,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
