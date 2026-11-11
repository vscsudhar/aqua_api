<?php

namespace App\Models;

use App\Traits\BelongsToCompany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'category_id',
        'unit_id',
        'name',
        'sku',
        'product_type',
        'unit',
        'description',
        'hsn_code',
        'default_price',
        'cost_price',
        'tax_rate',
        'current_stock',
        'track_stock',
        'min_stock_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'current_stock' => 'decimal:2',
            'track_stock' => 'boolean',
            'min_stock_level' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function unitRel(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function supplyItems(): HasMany
    {
        return $this->hasMany(DealerSupplyItem::class, 'product_id');
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'product_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_id');
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class, 'product_id');
    }

    public function isRefill(): bool
    {
        if (($this->product_type ?? 'normal') === 'refill') {
            return true;
        }

        // Also consider refill if name or SKU contains 'refill' / 'refil'
        $name = strtolower($this->name ?? '');
        $sku = strtolower($this->sku ?? '');

        return str_contains($name, 'refill') ||
            str_contains($name, 'refil') ||
            str_contains($sku, 'refill');
    }

    public function isNormal(): bool
    {
        return ! $this->isRefill();
    }

    public function isLowStock(): bool
    {
        // Refill products do not maintain physical inventory and never trigger stock alerts
        if ($this->isRefill() || ! $this->track_stock) {
            return false;
        }

        return (float) $this->current_stock <= (float) $this->min_stock_level;
    }

    /**
     * Checks if product has transactional records.
     */
    public function isReferencedInTransactions(): bool
    {
        return $this->supplyItems()->exists()
            || $this->purchaseItems()->exists()
            || $this->inventoryMovements()->exists();
    }
}
