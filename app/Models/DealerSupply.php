<?php

namespace App\Models;

use App\Traits\BelongsToCompany;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DealerSupply extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $table = 'dealer_supplies';

    protected $fillable = [
        'company_id',
        'supply_no',
        'idempotency_key',
        'dealer_id',
        'supply_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'adjustment_amount',
        'total_amount',
        'paid_amount',
        'outstanding_amount',
        'payment_status',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'supply_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class, 'dealer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DealerSupplyItem::class, 'dealer_supply_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DealerPayment::class, 'dealer_supply_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function ledgerEntry(): HasOne
    {
        return $this->hasOne(DealerLedgerEntry::class, 'reference_id')
            ->where('entry_type', 'supply');
    }

    /**
     * Recalculates total_amount, paid_amount, outstanding_amount, and payment_status.
     */
    public function recalculateFinancials(bool $save = true): void
    {
        if ($this->items()->exists()) {
            $subtotal = 0.0;
            $tax = 0.0;

            foreach ($this->items()->get() as $item) {
                $subtotal += (float) ($item->quantity * $item->applied_price);
                $tax += (float) $item->tax_amount;
            }

            $discount = (float) $this->discount_amount;
            $adjustment = (float) $this->adjustment_amount;
            $this->subtotal = $subtotal;
            $this->tax_amount = $tax;
            $this->total_amount = round($subtotal + $tax + $adjustment - $discount, 2);
        }

        $total = (float) $this->total_amount;
        $paid = (float) $this->payments()
            ->where('status', 'completed')
            ->sum('amount');

        $outstanding = round(max(0.0, $total - $paid), 2);

        $paymentStatus = 'unpaid';
        if ($paid >= $total && $total > 0) {
            $paymentStatus = 'paid';
        } elseif ($paid > 0) {
            $paymentStatus = 'partially_paid';
        }

        $this->paid_amount = $paid;
        $this->outstanding_amount = $outstanding;
        $this->payment_status = $paymentStatus;

        if ($save) {
            $this->save();
        }
    }

    /**
     * Synchronizes this supply transaction into the single source of truth ledger.
     */
    public function syncLedgerEntry(?int $userId = null): ?DealerLedgerEntry
    {
        if ($this->status === 'cancelled') {
            $this->ledgerEntry()?->delete();

            return null;
        }

        $entryDate = $this->supply_date ? $this->supply_date->toDateString() : Carbon::today('Asia/Kolkata')->toDateString();
        $total = (float) $this->total_amount;
        $dealer = $this->dealer;

        $entry = $this->ledgerEntry()->first();
        $narration = "Daily Supply: {$this->supply_no}".($dealer ? " to {$dealer->name}" : '');

        if ($entry) {
            $entry->update([
                'entry_date' => $entryDate,
                'reference_no' => $this->supply_no,
                'debit' => $total,
                'credit' => 0.00,
                'narration' => $narration,
            ]);

            return $entry;
        }

        return DealerLedgerEntry::create([
            'dealer_id' => $this->dealer_id,
            'entry_date' => $entryDate,
            'entry_type' => 'supply',
            'reference_id' => $this->id,
            'reference_no' => $this->supply_no,
            'debit' => $total,
            'credit' => 0.00,
            'narration' => $narration,
            'created_by' => $userId ?? $this->created_by,
        ]);
    }
}
