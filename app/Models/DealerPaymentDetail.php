<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DealerPaymentDetail extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $table = 'dealer_payment_details';

    protected $fillable = [
        'company_id',
        'dealer_payment_id',
        'payment_mode',
        'amount',
        'reference_no',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(DealerPayment::class, 'dealer_payment_id');
    }
}
