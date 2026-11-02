<?php

namespace App\Models;

use App\Traits\BelongsToCompany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealerLedgerEntry extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'dealer_id',
        'entry_date',
        'entry_type',
        'reference_id',
        'reference_no',
        'debit',
        'credit',
        'running_balance',
        'narration',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'running_balance' => 'decimal:2',
        ];
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
