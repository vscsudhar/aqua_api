<?php

namespace App\Models;

use App\Traits\BelongsToCompany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'movement_no',
        'movement_date',
        'product_id',
        'movement_type',
        'reference_type',
        'reference_id',
        'reference_no',
        'quantity',
        'signed_quantity',
        'unit',
        'unit_cost',
        'total_cost',
        'balance_before',
        'balance_after',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'quantity' => 'decimal:2',
            'signed_quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
