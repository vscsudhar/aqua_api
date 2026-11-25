<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicSaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_sale_id',
        'product_id',
        'product_name_snapshot',
        'unit_snapshot',
        'rate',
        'quantity',
        'amount',
        'stock_deducted',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'quantity' => 'decimal:2',
            'amount' => 'decimal:2',
            'stock_deducted' => 'decimal:2',
        ];
    }

    public function publicSale(): BelongsTo
    {
        return $this->belongsTo(PublicSale::class, 'public_sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
