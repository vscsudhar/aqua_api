<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanySettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'tagline' => $this->tagline,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'address' => $this->address,
            'state_name' => $this->state_name,
            'state_code' => $this->state_code,
            'gstin' => $this->gstin,
            'pan' => $this->pan,
            'bank_name' => $this->bank_name,
            'bank_branch' => $this->bank_branch,
            'account_number' => $this->account_number,
            'ifsc_code' => $this->ifsc_code,
            'upi_id' => $this->upi_id,
            'currency_symbol' => $this->currency_symbol ?? '₹',
            'currency_code' => $this->currency_code ?? 'INR',
            'default_tax_rate' => (float) ($this->default_tax_rate ?? 18.00),
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logo_url,
            'invoice_prefix' => $this->invoice_prefix ?? 'INV',
            'invoice_number_padding' => (int) ($this->invoice_number_padding ?? 4),
            'next_invoice_number' => (int) ($this->next_invoice_number ?? 1),
            'invoice_footer_notes' => $this->invoice_footer_notes,
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
