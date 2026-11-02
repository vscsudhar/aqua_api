<?php

namespace App\Models;

use App\Traits\BelongsToCompany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'adjustment_no',
        'adjustment_date',
        'product_id',
        'adjustment_type',
        'quantity',
        'unit',
        'cost_price',
        'stock_before',
        'stock_after',
        'reason',
        'authorized_by',
        'authorized_by_id',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
            'quantity' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock_before' => 'decimal:2',
            'stock_after' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function authorizedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_id');
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
