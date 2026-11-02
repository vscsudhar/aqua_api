<?php

namespace App\Models;

use App\Traits\BelongsToCompany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'gstin',
        'opening_balance',
        'current_balance',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function calculateOutstandingBalance(): float
    {
        $opening = (float) $this->opening_balance;
        $purchasesSum = (float) $this->purchases()
            ->whereIn('status', ['received', 'posted'])
            ->selectRaw('SUM(total_amount - paid_amount) as outstanding')
            ->value('outstanding');

        return round($opening + $purchasesSum, 2);
    }

    public function isReferencedInTransactions(): bool
    {
        return $this->purchases()->exists();
    }
}
