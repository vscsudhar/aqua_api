<?php

namespace App\Models;

use App\Traits\BelongsToCompany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'credit_note_no',
        'credit_note_date',
        'invoice_id',
        'dealer_id',
        'subtotal',
        'tax_amount',
        'total_amount',
        'reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'credit_note_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class, 'dealer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
