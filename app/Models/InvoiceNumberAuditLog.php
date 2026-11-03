<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceNumberAuditLog extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'old_invoice_no',
        'new_invoice_no',
        'reason',
        'changed_by',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

