<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\CreditNote;
use App\Models\Dealer;
use App\Models\DealerLedgerEntry;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BillingService
{
    /**
     * Calculate and format invoice item totals with verified GST tax rules (CGST/SGST vs IGST).
     */
    public function computeItemTax(
        float $quantity,
        float $unitPrice,
        float $discount,
        float $taxRate,
        bool $isInterstate
    ): array {
        $taxableAmount = round(max(0.0, ($quantity * $unitPrice) - $discount), 2);

        if ($isInterstate) {
            $cgstRate = 0.00;
            $cgstAmount = 0.00;
            $sgstRate = 0.00;
            $sgstAmount = 0.00;
            $igstRate = $taxRate;
            $igstAmount = round($taxableAmount * ($igstRate / 100), 2);
        } else {
            // Intra-state: split GST equally into CGST & SGST
            $cgstRate = round($taxRate / 2, 2);
            $cgstAmount = round($taxableAmount * ($cgstRate / 100), 2);
            $sgstRate = round($taxRate / 2, 2);
            $sgstAmount = round($taxableAmount * ($sgstRate / 100), 2);
            $igstRate = 0.00;
            $igstAmount = 0.00;
        }

        $lineTotal = round($taxableAmount + $cgstAmount + $sgstAmount + $igstAmount, 2);

        return [
            'taxable_amount' => $taxableAmount,
            'tax_rate' => $taxRate,
            'cgst_rate' => $cgstRate,
            'cgst_amount' => $cgstAmount,
            'sgst_rate' => $sgstRate,
            'sgst_amount' => $sgstAmount,
            'igst_rate' => $igstRate,
            'igst_amount' => $igstAmount,
            'line_total' => $lineTotal,
        ];
    }

    /**
     * Create or save an invoice (in draft or finalized status).
     */
    public function createInvoice(array $data, ?User $user = null): Invoice
    {
        // 1. Idempotency Check
        if (! empty($data['idempotency_key'])) {
            $existing = Invoice::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing->load(['dealer', 'supply', 'items.product', 'payments']);
            }
        }

        return DB::transaction(function () use ($data, $user) {
            $companyId = $data['company_id'] ?? $user?->company_id ?? Auth::user()?->company_id;
            $settings = CompanySetting::getSettings($companyId ? (int) $companyId : null);
            $invoiceDate = ! empty($data['invoice_date'])
                ? Carbon::parse($data['invoice_date'], 'Asia/Kolkata')->toDateString()
                : Carbon::today('Asia/Kolkata')->toDateString();

            // Resolve Customer / Dealer details
            $dealer = null;
            if (! empty($data['dealer_id'])) {
                $dealer = Dealer::find($data['dealer_id']);
            }

            $customerName = $data['customer_name'] ?? ($dealer?->name ?? 'Walk-in Customer');
            $customerPhone = $data['customer_phone'] ?? $dealer?->phone;
            $customerEmail = $data['customer_email'] ?? $dealer?->email;
            $customerAddress = $data['customer_address'] ?? $dealer?->address;
            $customerGstin = $data['customer_gstin'] ?? $dealer?->gstin;

            // Place of Supply & Tax Treatment
            $companyStateCode = $settings->state_code ?? '33';
            if (! empty($settings->gstin) && preg_match('/^\d{2}/', trim($settings->gstin), $gm)) {
                $companyStateCode = $gm[0];
            }
            $placeOfSupply = $data['place_of_supply'] ?? ($dealer?->state_code ?? '33-Tamil Nadu');

            // Determine if interstate by checking place of supply first, then valid 2-digit GSTIN prefix
            if (isset($data['is_interstate'])) {
                $isInterstate = (bool) $data['is_interstate'];
            } else {
                $posDigits = preg_match('/^\d{2}/', trim($placeOfSupply), $pm) ? $pm[0] : null;
                $gstinDigits = preg_match('/^\d{2}/', trim($customerGstin ?? ''), $gm) ? $gm[0] : null;

                if ($posDigits !== null) {
                    $isInterstate = ($posDigits !== $companyStateCode);
                } elseif ($gstinDigits !== null) {
                    $isInterstate = ($gstinDigits !== $companyStateCode);
                } else {
                    $isInterstate = false;
                }
            }

            // Generate unique Invoice No
            $dateSlug = Carbon::parse($invoiceDate)->format('Ymd');
            $prefix = $settings->invoice_prefix ?? 'INV';
            $padding = $settings->invoice_number_padding ?? 4;
            $invQuery = Invoice::withoutGlobalScopes()->withTrashed();
            if ($companyId) {
                $invQuery->where('company_id', $companyId);
            }
            $count = (clone $invQuery)->whereDate('invoice_date', $invoiceDate)->count() + 1;
            do {
                $invoiceNo = sprintf("%s-%s-%0{$padding}d", $prefix, $dateSlug, $count);
                $count++;
            } while ((clone $invQuery)->where('invoice_no', $invoiceNo)->exists());

            $isFinal = filter_var($data['is_final'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $status = (! empty($data['status']) && in_array($data['status'], ['draft', 'finalized']))
                ? $data['status']
                : ($isFinal ? 'finalized' : 'draft');

            $invoice = Invoice::create([
                'company_id' => $companyId,
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate,
                'due_date' => ! empty($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString() : null,
                'dealer_id' => $dealer?->id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
                'customer_address' => $customerAddress,
                'customer_gstin' => $customerGstin,
                'place_of_supply' => $placeOfSupply,
                'is_interstate' => $isInterstate,
                'dealer_supply_id' => $data['dealer_supply_id'] ?? null,
                'subtotal' => 0.00,
                'discount_total' => 0.00,
                'taxable_amount' => 0.00,
                'cgst_amount' => 0.00,
                'sgst_amount' => 0.00,
                'igst_amount' => 0.00,
                'tax_total' => 0.00,
                'round_off' => 0.00,
                'grand_total' => 0.00,
                'paid_amount' => 0.00,
                'balance_due' => 0.00,
                'status' => $status,
                'payment_status' => 'unpaid',
                'finalized_at' => $status === 'finalized' ? now() : null,
                'finalized_by' => $status === 'finalized' ? $user?->id : null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? $settings->invoice_footer_notes,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'created_by' => $user?->id,
            ]);

            // Save line items
            $this->saveInvoiceItems($invoice, $data['items'] ?? [], $isInterstate);

            // If finalized immediately and not linked to an existing supply, synchronize ledger
            if ($status === 'finalized') {
                $this->syncFinalizedLedgerEntry($invoice, $user);
            }

            // Record immediate payment if provided
            $paymentAmount = (float) ($data['payment_amount'] ?? $data['paid_amount'] ?? 0);
            if ($paymentAmount > 0) {
                $this->recordPayment($invoice, [
                    'amount' => $paymentAmount,
                    'payment_date' => $invoiceDate,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'reference_no' => $data['payment_reference'] ?? null,
                    'notes' => 'Received at time of invoice creation',
                ], $user);
            }

            return $invoice->load(['dealer', 'supply', 'items.product', 'payments']);
        });
    }

    /**
     * Update a draft invoice. Finalized invoices are strictly protected against modification.
     */
    public function updateInvoice(Invoice $invoice, array $data, ?User $user = null): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Finalized invoices cannot be modified. Issue a credit note or cancel the invoice instead.'],
            ]);
        }

        return DB::transaction(function () use ($invoice, $data, $user) {
            $settings = CompanySetting::getSettings();
            $invoiceDate = ! empty($data['invoice_date'])
                ? Carbon::parse($data['invoice_date'], 'Asia/Kolkata')->toDateString()
                : $invoice->invoice_date->toDateString();

            $dealer = null;
            if (! empty($data['dealer_id'])) {
                $dealer = Dealer::find($data['dealer_id']);
            }

            $companyStateCode = $settings->state_code ?? '33';
            if (! empty($settings->gstin) && preg_match('/^\d{2}/', trim($settings->gstin), $gm)) {
                $companyStateCode = $gm[0];
            }
            $placeOfSupply = $data['place_of_supply'] ?? $invoice->place_of_supply;
            $customerGstin = $data['customer_gstin'] ?? ($dealer?->gstin ?? $invoice->customer_gstin);

            // Determine if interstate by checking place of supply first, then valid 2-digit GSTIN prefix
            if (isset($data['is_interstate'])) {
                $isInterstate = (bool) $data['is_interstate'];
            } else {
                $posDigits = preg_match('/^\d{2}/', trim($placeOfSupply), $pm) ? $pm[0] : null;
                $gstinDigits = preg_match('/^\d{2}/', trim($customerGstin ?? ''), $gm) ? $gm[0] : null;

                if ($posDigits !== null) {
                    $isInterstate = ($posDigits !== $companyStateCode);
                } elseif ($gstinDigits !== null) {
                    $isInterstate = ($gstinDigits !== $companyStateCode);
                } else {
                    $isInterstate = false;
                }
            }

            $invoice->invoice_date = $invoiceDate;
            if (isset($data['due_date'])) {
                $invoice->due_date = ! empty($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString() : null;
            }
            if (! empty($data['dealer_id'])) {
                $invoice->dealer_id = $data['dealer_id'];
            }
            if (isset($data['customer_name'])) {
                $invoice->customer_name = $data['customer_name'];
            }
            if (isset($data['customer_phone'])) {
                $invoice->customer_phone = $data['customer_phone'];
            }
            if (isset($data['customer_email'])) {
                $invoice->customer_email = $data['customer_email'];
            }
            if (isset($data['customer_address'])) {
                $invoice->customer_address = $data['customer_address'];
            }
            $invoice->customer_gstin = $customerGstin;
            $invoice->place_of_supply = $placeOfSupply;
            $invoice->is_interstate = $isInterstate;
            if (isset($data['notes'])) {
                $invoice->notes = $data['notes'];
            }
            if (isset($data['terms'])) {
                $invoice->terms = $data['terms'];
            }
            $invoice->updated_by = $user?->id;

            // Re-save line items
            $invoice->items()->delete();
            $this->saveInvoiceItems($invoice, $data['items'] ?? [], $isInterstate);

            // If updating status to finalized
            $isFinal = filter_var($data['is_final'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ((isset($data['status']) && $data['status'] === 'finalized') || $isFinal) {
                $invoice->status = 'finalized';
                $invoice->finalized_at = now();
                $invoice->finalized_by = $user?->id;
                $invoice->save();

                $this->syncFinalizedLedgerEntry($invoice, $user);
            } else {
                $invoice->save();
            }

            return $invoice->load(['dealer', 'supply', 'items.product', 'payments']);
        });
    }

    /**
     * Finalize an invoice: locks it from further modifications and records ledger debit if standalone sale.
     */
    public function finalizeInvoice(Invoice $invoice, ?User $user = null): Invoice
    {
        if ($invoice->status === 'finalized') {
            return $invoice;
        }

        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => ['Cancelled invoices cannot be finalized.'],
            ]);
        }

        return DB::transaction(function () use ($invoice, $user) {
            $invoice->status = 'finalized';
            $invoice->finalized_at = now();
            $invoice->finalized_by = $user?->id;
            $invoice->save();

            // Synchronize ledger debit if standalone sale
            $this->syncFinalizedLedgerEntry($invoice, $user);

            return $invoice->load(['dealer', 'supply', 'items.product', 'payments']);
        });
    }

    /**
     * Cancel an invoice with reason and credit offset if needed.
     */
    public function cancelInvoice(Invoice $invoice, string $reason, ?User $user = null): Invoice
    {
        if ($invoice->status === 'cancelled') {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice, $reason, $user) {
            // If it was finalized and standalone (not linked to an existing supply) and dealer linked:
            // Reverse the debit on dealer ledger
            if ($invoice->status === 'finalized' && empty($invoice->dealer_supply_id) && ! empty($invoice->dealer_id)) {
                $dealer = $invoice->dealer;
                if ($dealer) {
                    $runningBalance = $dealer->calculateOutstandingBalance();
                    $newBalance = round($runningBalance - (float) $invoice->grand_total, 2);

                    DealerLedgerEntry::create([
                        'dealer_id' => $dealer->id,
                        'entry_date' => now()->toDateString(),
                        'entry_type' => 'adjustment',
                        'reference_id' => $invoice->id,
                        'reference_no' => $invoice->invoice_no.'-CAN',
                        'debit' => 0.00,
                        'credit' => (float) $invoice->grand_total,
                        'running_balance' => $newBalance,
                        'narration' => "Cancelled Invoice {$invoice->invoice_no}: {$reason}",
                        'created_by' => $user?->id,
                    ]);
                }
            }

            $invoice->status = 'cancelled';
            $invoice->cancellation_reason = $reason;
            $invoice->cancelled_at = now();
            $invoice->cancelled_by = $user?->id;
            $invoice->save();

            return $invoice->load(['dealer', 'supply', 'items.product', 'payments']);
        });
    }

    /**
     * Create a formal credit note for an invoice.
     */
    public function createCreditNote(Invoice $invoice, array $data, ?User $user = null): CreditNote
    {
        return DB::transaction(function () use ($invoice, $data, $user) {
            $companyId = $data['company_id'] ?? $invoice->company_id ?? $user?->company_id ?? Auth::user()?->company_id;
            $date = ! empty($data['credit_note_date'])
                ? Carbon::parse($data['credit_note_date'])->toDateString()
                : Carbon::today('Asia/Kolkata')->toDateString();

            $dateSlug = Carbon::parse($date)->format('Ymd');
            $cnQuery = CreditNote::withoutGlobalScopes();
            if (method_exists(CreditNote::class, 'withTrashed')) {
                $cnQuery = CreditNote::withoutGlobalScopes()->withTrashed();
            }
            if ($companyId) {
                $cnQuery->where('company_id', $companyId);
            }
            $count = (clone $cnQuery)->whereDate('credit_note_date', $date)->count() + 1;
            do {
                $cnNo = sprintf('CN-%s-%04d', $dateSlug, $count);
                $count++;
            } while ((clone $cnQuery)->where('credit_note_no', $cnNo)->exists());

            $subtotal = (float) ($data['subtotal'] ?? $invoice->taxable_amount);
            $taxAmount = (float) ($data['tax_amount'] ?? $invoice->tax_total);
            $totalAmount = (float) ($data['total_amount'] ?? ($subtotal + $taxAmount));

            $creditNote = CreditNote::create([
                'company_id' => $companyId,
                'credit_note_no' => $cnNo,
                'credit_note_date' => $date,
                'invoice_id' => $invoice->id,
                'dealer_id' => $invoice->dealer_id,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'reason' => $data['reason'] ?? 'Goods returned / rate revision',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            // Sync credit to dealer ledger
            if ($invoice->dealer_id) {
                $dealer = $invoice->dealer;
                $runningBalance = $dealer->calculateOutstandingBalance();
                $newBalance = round($runningBalance - $totalAmount, 2);

                DealerLedgerEntry::create([
                    'dealer_id' => $dealer->id,
                    'entry_date' => $date,
                    'entry_type' => 'adjustment',
                    'reference_id' => $creditNote->id,
                    'reference_no' => $creditNote->credit_note_no,
                    'debit' => 0.00,
                    'credit' => $totalAmount,
                    'running_balance' => $newBalance,
                    'narration' => "Credit Note {$creditNote->credit_note_no} for Inv {$invoice->invoice_no}: {$creditNote->reason}",
                    'created_by' => $user?->id,
                ]);
            }

            return $creditNote;
        });
    }

    /**
     * Record a payment receipt against an invoice.
     */
    public function recordPayment(Invoice $invoice, array $data, ?User $user = null): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $data, $user) {
            $companyId = $data['company_id'] ?? $invoice->company_id ?? $user?->company_id ?? Auth::user()?->company_id;
            $paymentDate = ! empty($data['payment_date'])
                ? Carbon::parse($data['payment_date'])->toDateString()
                : Carbon::today('Asia/Kolkata')->toDateString();

            $dateSlug = Carbon::parse($paymentDate)->format('Ymd');
            $payQuery = InvoicePayment::withoutGlobalScopes();
            if (method_exists(InvoicePayment::class, 'withTrashed')) {
                $payQuery = InvoicePayment::withoutGlobalScopes()->withTrashed();
            }
            if ($companyId) {
                $payQuery->where('company_id', $companyId);
            }
            $count = (clone $payQuery)->whereDate('payment_date', $paymentDate)->count() + 1;
            do {
                $paymentNo = sprintf('INV-PAY-%s-%04d', $dateSlug, $count);
                $count++;
            } while ((clone $payQuery)->where('payment_no', $paymentNo)->exists());

            $amount = (float) $data['amount'];

            $payment = InvoicePayment::create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'payment_no' => $paymentNo,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            // Recompute invoice payment totals
            $totalPaid = (float) $invoice->payments()->sum('amount');
            $invoice->paid_amount = round($totalPaid, 2);
            $invoice->balance_due = round(max(0.0, (float) $invoice->grand_total - $totalPaid), 2);

            if ($invoice->paid_amount >= (float) $invoice->grand_total) {
                $invoice->payment_status = 'paid';
            } elseif ($invoice->paid_amount > 0) {
                $invoice->payment_status = 'partially_paid';
            } else {
                $invoice->payment_status = 'unpaid';
            }
            $invoice->save();

            // Sync credit to dealer ledger if standalone dealer invoice and finalized
            if (! empty($invoice->dealer_id) && empty($invoice->dealer_supply_id) && $invoice->status === 'finalized') {
                $dealer = $invoice->dealer;
                if ($dealer) {
                    $runningBalance = $dealer->calculateOutstandingBalance();
                    $newBalance = round($runningBalance - $amount, 2);

                    DealerLedgerEntry::create([
                        'dealer_id' => $dealer->id,
                        'entry_date' => $paymentDate,
                        'entry_type' => 'payment',
                        'reference_id' => $payment->id,
                        'reference_no' => $payment->payment_no,
                        'debit' => 0.00,
                        'credit' => $amount,
                        'running_balance' => $newBalance,
                        'narration' => "Payment for Tax Invoice {$invoice->invoice_no} ({$payment->payment_method})",
                        'created_by' => $user?->id,
                    ]);
                }
            }

            return $payment;
        });
    }

    /**
     * Save line items and recalculate invoice totals.
     */
    protected function saveInvoiceItems(Invoice $invoice, array $items, bool $isInterstate): void
    {
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => ['At least one invoice line item is required.'],
            ]);
        }

        $subtotal = 0.00;
        $discountTotal = 0.00;
        $taxableAmountTotal = 0.00;
        $cgstTotal = 0.00;
        $sgstTotal = 0.00;
        $igstTotal = 0.00;

        foreach ($items as $itemData) {
            $product = ! empty($itemData['product_id']) ? Product::find($itemData['product_id']) : null;
            $qty = (float) $itemData['quantity'];
            $price = (float) $itemData['unit_price'];
            $discount = isset($itemData['discount_amount']) ? (float) $itemData['discount_amount'] : 0.00;
            $taxRate = isset($itemData['tax_rate']) ? (float) $itemData['tax_rate'] : (float) ($product?->tax_rate ?? 0.00);

            $calc = $this->computeItemTax($qty, $price, $discount, $taxRate, $isInterstate);

            $subtotal += round($qty * $price, 2);
            $discountTotal += $discount;
            $taxableAmountTotal += $calc['taxable_amount'];
            $cgstTotal += $calc['cgst_amount'];
            $sgstTotal += $calc['sgst_amount'];
            $igstTotal += $calc['igst_amount'];

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $product?->id,
                'item_description' => $itemData['item_description'] ?? ($product?->name ?? 'Custom Item'),
                'hsn_sac' => $itemData['hsn_sac'] ?? ($product?->hsn_code ?? '2201'),
                'quantity' => $qty,
                'unit' => $itemData['unit'] ?? ($product?->unit ?? 'Unit'),
                'unit_price' => $price,
                'discount_amount' => $discount,
                'taxable_amount' => $calc['taxable_amount'],
                'tax_rate' => $calc['tax_rate'],
                'cgst_rate' => $calc['cgst_rate'],
                'cgst_amount' => $calc['cgst_amount'],
                'sgst_rate' => $calc['sgst_rate'],
                'sgst_amount' => $calc['sgst_amount'],
                'igst_rate' => $calc['igst_rate'],
                'igst_amount' => $calc['igst_amount'],
                'line_total' => $calc['line_total'],
                'notes' => $itemData['notes'] ?? null,
            ]);
        }

        $taxTotal = round($cgstTotal + $sgstTotal + $igstTotal, 2);
        $rawGrand = round($taxableAmountTotal + $taxTotal, 2);
        $roundedGrand = round($rawGrand);
        $roundOff = round($roundedGrand - $rawGrand, 2);

        $invoice->subtotal = $subtotal;
        $invoice->discount_total = $discountTotal;
        $invoice->taxable_amount = $taxableAmountTotal;
        $invoice->cgst_amount = $cgstTotal;
        $invoice->sgst_amount = $sgstTotal;
        $invoice->igst_amount = $igstTotal;
        $invoice->tax_total = $taxTotal;
        $invoice->round_off = $roundOff;
        $invoice->grand_total = $roundedGrand;
        $invoice->balance_due = $roundedGrand;
        $invoice->save();
    }

    /**
     * Synchronize finalized invoice with dealer ledger if standalone sale.
     * Prevents double counting if derived from an existing dealer supply.
     */
    protected function syncFinalizedLedgerEntry(Invoice $invoice, ?User $user = null): void
    {
        // If invoice is derived from an existing dealer supply:
        // Do NOT duplicate ledger debit! The supply transaction already debited dealer ledger.
        if (! empty($invoice->dealer_supply_id)) {
            return;
        }

        // If it's a direct standalone invoice and linked to a dealer:
        if (! empty($invoice->dealer_id)) {
            $dealer = $invoice->dealer;
            if ($dealer) {
                // Check if already has ledger entry
                $exists = DealerLedgerEntry::where('dealer_id', $dealer->id)
                    ->where('entry_type', 'supply')
                    ->where('reference_no', $invoice->invoice_no)
                    ->exists();

                if (! $exists) {
                    $runningBalance = $dealer->calculateOutstandingBalance();
                    $newBalance = round($runningBalance + (float) $invoice->grand_total, 2);

                    DealerLedgerEntry::create([
                        'dealer_id' => $dealer->id,
                        'entry_date' => $invoice->invoice_date->toDateString(),
                        'entry_type' => 'supply',
                        'reference_id' => $invoice->id,
                        'reference_no' => $invoice->invoice_no,
                        'debit' => (float) $invoice->grand_total,
                        'credit' => 0.00,
                        'running_balance' => $newBalance,
                        'narration' => "Tax Invoice {$invoice->invoice_no} ({$invoice->customer_name})",
                        'created_by' => $user?->id,
                    ]);
                }
            }
        }
    }

    /**
     * Render professional A4 PDF for an invoice.
     */
    public function renderInvoicePdf(Invoice $invoice)
    {
        $settings = CompanySetting::getSettings($invoice->company_id);
        $invoice->load(['dealer', 'supply', 'items.product', 'payments']);

        $logoBase64 = null;
        $logoPath = $settings->logo_path ?: $invoice->company?->logo_path;
        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            $absPath = Storage::disk('public')->path($logoPath);
            $mime = @mime_content_type($absPath) ?: 'image/png';
            $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absPath));
        }

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'settings' => $settings,
            'logoBase64' => $logoBase64,
            'amountInWords' => $this->numberToIndianWords((float) $invoice->grand_total),
        ])->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Convert numeric monetary amount into Indian Rupee words (e.g. "Three Thousand Five Hundred").
     */
    public function numberToIndianWords(float $number): string
    {
        $no = floor($number);
        $decimal = round(($number - $no) * 100);
        $digits_length = strlen($no);
        $i = 0;
        $str = [];
        $words = [
            0 => '', 1 => 'One', 2 => 'Two',
            3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
            7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
            13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
            19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
            40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
            70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety',
        ];
        $digits = ['', 'Hundred', 'Thousand', 'Lakh', 'Crore'];
        while ($i < $digits_length) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str[] = ($number < 21) ? $words[$number].' '.$digits[$counter].$plural.' '.$hundred
                    : $words[floor($number / 10) * 10].' '.$words[$number % 10].' '.$digits[$counter].$plural.' '.$hundred;
            } else {
                $str[] = null;
            }
        }
        $Rupees = implode('', array_reverse($str));
        $paise = ($decimal > 0) ? ' and '.($words[$decimal / 10] ?? '').' '.($words[$decimal % 10] ?? '').' Paise' : '';

        return ($Rupees ? 'Rupees '.trim($Rupees) : 'Zero Rupees').$paise.' Only';
    }
}
