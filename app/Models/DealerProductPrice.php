<?php

namespace App\Models;

use App\Traits\BelongsToCompany;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealerProductPrice extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'dealer_product_prices';

    protected $fillable = [
        'company_id',
        'dealer_id',
        'product_id',
        'custom_price',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'custom_price' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class, 'dealer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectiveOn(Builder $query, ?string $date = null): Builder
    {
        $targetDate = $date ? Carbon::parse($date)->toDateString() : Carbon::today('Asia/Kolkata')->toDateString();

        return $query->where('effective_from', '<=', $targetDate)
            ->where(function (Builder $q) use ($targetDate) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $targetDate);
            });
    }

    public function isCurrentlyEffective(?string $date = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $targetDate = $date ? Carbon::parse($date)->toDateString() : Carbon::today('Asia/Kolkata')->toDateString();

        if ($this->effective_from->toDateString() > $targetDate) {
            return false;
        }

        if ($this->effective_to && $this->effective_to->toDateString() < $targetDate) {
            return false;
        }

        return true;
    }
}
