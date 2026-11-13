<?php

namespace App\Models;

use App\Traits\BelongsToCompany;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DealerPayment extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $table = 'dealer_payments';

    protected $fillable = [
        'company_id',
        'payment_no',
        'idempotency_key',
        'dealer_id',
        'dealer_supply_id',
        'payment_date',
        'amount',
        'payment_method',
        'payment_mode',
        'reference_no',
        'status',
        'remarks',
        'notes',
        'collected_by',
        'created_by',
        'updated_by',
        'include_in_ledger_amount',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'include_in_ledger_amount' => 'boolean',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class, 'dealer_id');
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(DealerSupply::class, 'dealer_supply_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
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
            ->where('entry_type', 'payment');
    }

    /**
     * Synchronizes this collection into the ledger immediately.
     */
    public function syncLedgerEntry(?int $userId = null): ?DealerLedgerEntry
    {
        if ($this->status !== 'completed') {
            $this->ledgerEntry()?->delete();

            return null;
        }

        $entryDate = $this->payment_date ? $this->payment_date->toDateString() : Carbon::today('Asia/Kolkata')->toDateString();
        $amount = (float) $this->amount;
        $dealer = $this->dealer;
        $method = strtoupper($this->payment_method ?? 'CASH');

        $narration = "Collection: {$this->payment_no} via {$method}".($this->reference_no ? " (Ref: {$this->reference_no})" : '');
        if ($dealer) {
            $narration .= " from {$dealer->name}";
        }

        $entry = $this->ledgerEntry()->first();

        if ($entry) {
            $entry->update([
                'entry_date' => $entryDate,
                'reference_no' => $this->payment_no,
                'debit' => 0.00,
                'credit' => $amount,
                'narration' => $narration,
            ]);

            return $entry;
        }

        return DealerLedgerEntry::create([
            'dealer_id' => $this->dealer_id,
            'entry_date' => $entryDate,
            'entry_type' => 'payment',
            'reference_id' => $this->id,
            'reference_no' => $this->payment_no,
            'debit' => 0.00,
            'credit' => $amount,
            'narration' => $narration,
            'created_by' => $userId ?? $this->created_by,
        ]);
    }
}
