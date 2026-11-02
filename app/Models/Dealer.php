<?php

namespace App\Models;

use App\Traits\BelongsToCompany;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dealer extends Model
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
        'credit_limit',
        'opening_balance',
        'opening_date',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'opening_balance' => 'decimal:2',
            'opening_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(Supply::class, 'dealer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'dealer_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(DealerLedgerEntry::class, 'dealer_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'dealer_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class, 'dealer_id');
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(DealerProductPrice::class, 'dealer_id');
    }

    public function openingBalanceLedgerEntry(): HasOne
    {
        return $this->hasOne(DealerLedgerEntry::class, 'dealer_id')
            ->where('entry_type', 'opening_balance');
    }

    /**
     * Resolves the effective product price for this dealer on any given date.
     * Checks custom price first, otherwise falls back to the product's catalog default price.
     */
    public function getEffectiveProductPrice(int $productId, ?string $date = null): array
    {
        $targetDate = $date ? Carbon::parse($date)->toDateString() : Carbon::today('Asia/Kolkata')->toDateString();

        $customPrice = $this->productPrices()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->effectiveOn($targetDate)
            ->orderBy('effective_from', 'desc')
            ->first();

        $product = Product::find($productId);
        $catalogPrice = $product ? (float) $product->default_price : 0.0;

        if ($customPrice) {
            return [
                'price' => (float) $customPrice->custom_price,
                'is_custom' => true,
                'custom_price_id' => $customPrice->id,
                'catalog_price' => $catalogPrice,
                'effective_from' => $customPrice->effective_from?->toDateString(),
                'effective_to' => $customPrice->effective_to?->toDateString(),
            ];
        }

        return [
            'price' => $catalogPrice,
            'is_custom' => false,
            'custom_price_id' => null,
            'catalog_price' => $catalogPrice,
            'effective_from' => null,
            'effective_to' => null,
        ];
    }

    /**
     * Outstanding balance derived strictly from stored records.
     * Opening Balance + Delivered Supplies - Completed Payments
     */
    public function calculateOutstandingBalance(): float
    {
        $deliveredSupplies = (float) $this->supplies()
            ->where('status', 'delivered')
            ->sum('total_amount');

        // Only standalone finalized invoices (derived invoices are already counted in supplies)
        $standaloneInvoices = (float) $this->invoices()
            ->whereNull('dealer_supply_id')
            ->where('status', 'finalized')
            ->sum('grand_total');

        $completedPayments = (float) $this->payments()
            ->where('status', 'completed')
            ->sum('amount');

        $standaloneInvoicePayments = (float) InvoicePayment::whereHas('invoice', function ($q) {
            $q->where('dealer_id', $this->id)->whereNull('dealer_supply_id');
        })->sum('amount');

        $creditNotesTotal = (float) $this->creditNotes()->sum('total_amount');

        return round((float) $this->opening_balance + $deliveredSupplies + $standaloneInvoices - $completedPayments - $standaloneInvoicePayments - $creditNotesTotal, 2);
    }

    public function isReferencedInTransactions(): bool
    {
        return $this->supplies()->exists() || $this->payments()->exists();
    }

    /**
     * Creates or updates the single opening balance ledger entry.
     * Ensures opening balance has an effective date and is NEVER added twice.
     */
    public function syncOpeningBalanceLedger(?int $userId = null): ?DealerLedgerEntry
    {
        $openingBal = (float) $this->opening_balance;
        $entryDate = $this->opening_date ? $this->opening_date->toDateString() : Carbon::today('Asia/Kolkata')->toDateString();

        $entry = $this->openingBalanceLedgerEntry()->first();

        if ($openingBal == 0) {
            if ($entry) {
                $entry->delete();
            }

            return null;
        }

        $debit = $openingBal >= 0 ? $openingBal : 0.0;
        $credit = $openingBal < 0 ? abs($openingBal) : 0.0;

        if ($entry) {
            $entry->update([
                'entry_date' => $entryDate,
                'reference_no' => 'OPB-'.$this->code,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $openingBal,
                'narration' => 'Opening balance on dealer onboarding',
            ]);

            return $entry;
        }

        return DealerLedgerEntry::create([
            'dealer_id' => $this->id,
            'entry_date' => $entryDate,
            'entry_type' => 'opening_balance',
            'reference_id' => $this->id,
            'reference_no' => 'OPB-'.$this->code,
            'debit' => $debit,
            'credit' => $credit,
            'running_balance' => $openingBal,
            'narration' => 'Opening balance on dealer onboarding',
            'created_by' => $userId,
        ]);
    }
}
