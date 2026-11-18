<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BottleOtherEntry extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $table = 'bottle_other_entries';

    protected $fillable = [
        'company_id',
        'entry_date',
        'product_id',
        'quantity',
        'amount',
        'total_amount',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'quantity' => 'integer',
            'amount' => 'float',
            'total_amount' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BottleOtherEntry $entry) {
            $qty = (int) $entry->quantity;
            $amt = (float) $entry->amount;
            $entry->total_amount = round($qty * $amt, 2);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
