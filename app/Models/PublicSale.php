<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicSale extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'party_id',
        'sale_date',
        'sale_time',
        'cash_amount',
        'upi_amount',
        'total_amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'cash_amount' => 'decimal:2',
            'upi_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(PublicParty::class, 'party_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PublicSaleItem::class, 'public_sale_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'reference_id')
            ->where('reference_type', 'public_sale');
    }
}
