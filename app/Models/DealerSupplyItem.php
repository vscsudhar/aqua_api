<?php

namespace App\Models;

use App\Traits\BelongsToCompany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealerSupplyItem extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'dealer_supply_items';

    protected $fillable = [
        'company_id',
        'dealer_supply_id',
        'supply_id',
        'product_id',
        'quantity',
        'unit',
        'catalog_price',
        'applied_price',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'line_total',
        'total_amount',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (DealerSupplyItem $item) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('dealer_supply_items', 'supply_id')) {
                    if ($item->dealer_supply_id && ! $item->supply_id) {
                        $item->supply_id = $item->dealer_supply_id;
                    } elseif ($item->supply_id && ! $item->dealer_supply_id) {
                        $item->dealer_supply_id = $item->supply_id;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore schema check errors
            }
            if ($item->applied_price && ! $item->unit_price) {
                $item->unit_price = $item->applied_price;
            }
            if ($item->line_total && ! $item->total_amount) {
                $item->total_amount = $item->line_total;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'catalog_price' => 'decimal:2',
            'applied_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(DealerSupply::class, 'dealer_supply_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
