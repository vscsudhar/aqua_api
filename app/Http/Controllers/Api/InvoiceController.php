<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreditNoteResource;
use App\Http\Resources\InvoicePaymentResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function __construct(
        protected BillingService $billingService
    ) {}

    /**
     * List invoices with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['dealer', 'supply', 'items', 'creator', 'customerCompany'])
            ->withCount('items')
            ->orderBy('invoice_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->has('dealer_id') && ! empty($request->query('dealer_id'))) {
            $query->where('dealer_id', $request->query('dealer_id'));
        }

        if ($request->has('status') && ! empty($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('payment_status') && ! empty($request->query('payment_status'))) {
            $query->where('payment_status', $request->query('payment_status'));
        }

        if ($request->has('invoice_type') && ! empty($request->query('invoice_type'))) {
            $invType = $request->query('invoice_type');
            if ($invType === 'without_gst') {
                $query->where('invoice_type', 'without_gst');
            } elseif ($invType === 'with_gst') {
                $query->where(function ($q) {
                    $q->where('invoice_type', 'with_gst')
                        ->orWhereNull('invoice_type');
                });
            }
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('invoice_date', [
                $request->query('start_date'),
                $request->query('end_date'),
            ]);
        } elseif ($request->has('date')) {
            $query->whereDate('invoice_date', $request->query('date'));
        }

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_gstin', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $invoices = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Invoices retrieved successfully.',
            'data' => InvoiceResource::collection($invoices),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * Create a new invoice (draft or finalized).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_no' => ['nullable', 'string', 'max:50'],
            'customer_type' => ['nullable', 'string', 'in:dealer,company,direct'],
            'customer_company_id' => ['nullable', 'exists:companies,id'],
            'dealer_id' => ['nullable', 'exists:dealers,id'],
            'dealer_supply_id' => ['nullable', 'exists:dealer_supplies,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_address' => ['nullable', 'string'],
            'customer_gstin' => ['nullable', 'string', 'max:50'],
            'place_of_supply' => ['nullable', 'string', 'max:100'],
            'is_interstate' => ['nullable', 'boolean'],
            'invoice_type' => ['nullable', 'string', 'in:with_gst,without_gst'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_mode' => ['nullable', 'string', 'max:50'],
            'additional_taxes' => ['nullable', 'array'],
            'additional_taxes.*.name' => ['nullable', 'string', 'max:100'],
            'additional_taxes.*.rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'additional_taxes.*.amount' => ['nullable', 'numeric', 'min:0'],
            'additional_tax_total' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'in:draft,finalized'],
            'is_final' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'show_bank_details' => ['nullable', 'boolean'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.item_description' => ['required', 'string', 'max:255'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.taxable_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.cgst_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.cgst_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.sgst_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.sgst_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.igst_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.igst_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.line_total' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $invoice = $this->billingService->createInvoice($validated, $request->user());
        $isNew = $invoice->wasRecentlyCreated;

        return response()->json([
            'status' => 'success',
            'message' => $isNew ? 'Invoice created successfully.' : 'Existing invoice retrieved (idempotent).',
            'data' => new InvoiceResource($invoice),
        ], $isNew ? 201 : 200);
    }

    /**
     * Show detailed invoice with items, payments and credit notes.
     */
    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::with(['dealer', 'customerCompany', 'supply', 'items.product', 'payments.creator', 'creditNotes.creator', 'creator', 'finalizer', 'canceller'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice details retrieved.',
            'data' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * Update draft invoice.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'invoice_no' => ['nullable', 'string', 'max:50'],
            'customer_type' => ['nullable', 'string', 'in:dealer,company,direct'],
            'customer_company_id' => ['nullable', 'exists:companies,id'],
            'dealer_id' => ['nullable', 'exists:dealers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_address' => ['nullable', 'string'],
            'customer_gstin' => ['nullable', 'string', 'max:50'],
            'place_of_supply' => ['nullable', 'string', 'max:100'],
            'is_interstate' => ['nullable', 'boolean'],
            'invoice_type' => ['nullable', 'string', 'in:with_gst,without_gst'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_mode' => ['nullable', 'string', 'max:50'],
            'additional_taxes' => ['nullable', 'array'],
            'additional_taxes.*.name' => ['nullable', 'string', 'max:100'],
            'additional_taxes.*.rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'additional_taxes.*.amount' => ['nullable', 'numeric', 'min:0'],
            'additional_tax_total' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'in:draft,finalized'],
            'is_final' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'show_bank_details' => ['nullable', 'boolean'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.item_description' => ['required', 'string', 'max:255'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.taxable_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.cgst_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.cgst_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.sgst_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.sgst_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.igst_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.igst_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.line_total' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $this->billingService->updateInvoice($invoice, $validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice updated successfully.',
            'data' => new InvoiceResource($updated),
        ]);
    }

    /**
     * Finalize draft invoice.
     */
    public function finalize(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);
        $finalized = $this->billingService->finalizeInvoice($invoice, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice finalized successfully. It is now official and locked from further edits.',
            'data' => new InvoiceResource($finalized),
        ]);
    }

    /**
     * Cancel an invoice with mandatory reason.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        $reason = $request->input('reason') ?? $request->input('cancellation_reason');
        if (empty($reason)) {
            $request->validate([
                'reason' => ['required', 'string', 'max:255'],
            ]);
        }

        $cancelled = $this->billingService->cancelInvoice(
            $invoice,
            $reason,
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice cancelled successfully.',
            'data' => new InvoiceResource($cancelled),
        ]);
    }

    /**
     * Create credit note for invoice.
     */
    public function creditNote(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'credit_note_date' => ['nullable', 'date'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $creditNote = $this->billingService->createCreditNote($invoice, $validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Credit note generated and ledger credited.',
            'data' => new CreditNoteResource($creditNote),
        ], 201);
    }

    /**
     * Record payment on an invoice.
     */
    public function recordPayment(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $this->billingService->recordPayment($invoice, $validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Payment recorded against invoice.',
            'data' => new InvoicePaymentResource($payment),
            'invoice' => new InvoiceResource($invoice->fresh()),
        ], 201);
    }

    /**
     * Check if the company owning the invoice has an expired subscription.
     */
    protected function isInvoiceCompanyExpired(Invoice $invoice): bool
    {
        $company = $invoice->company;
        if (! $company) {
            return false;
        }

        if ($company->status === 'inactive') {
            return true;
        }

        if (! empty($company->expires_at)) {
            $expiresAt = \Carbon\Carbon::parse($company->expires_at, 'Asia/Kolkata')->endOfDay();
            if ($expiresAt->isPast()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Download or stream PDF invoice.
     */
    public function downloadPdf(int $id): Response
    {
        $invoice = Invoice::findOrFail($id);

        if ($this->isInvoiceCompanyExpired($invoice)) {
            return response()->make('Your plan has expired. Please renew your subscription. Contact SS Aqua Flow.', 403, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $pdf = $this->billingService->renderInvoicePdf($invoice);

        return $pdf->download("Invoice-{$invoice->invoice_no}.pdf");
    }

    /**
     * Print / preview PDF inline in browser.
     */
    public function printPdf(int $id): Response
    {
        $invoice = Invoice::findOrFail($id);

        if ($this->isInvoiceCompanyExpired($invoice)) {
            return response()->make('Your plan has expired. Please renew your subscription. Contact SS Aqua Flow.', 403, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $pdf = $this->billingService->renderInvoicePdf($invoice);

        return $pdf->stream("Invoice-{$invoice->invoice_no}.pdf");
    }

    /**
     * View full HTML invoice in browser.
     */
    public function showHtml(int $id)
    {
        $invoice = Invoice::with(['company', 'dealer', 'supply', 'items.product', 'payments'])->findOrFail($id);

        if ($this->isInvoiceCompanyExpired($invoice)) {
            $expiredHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Subscription Expired</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f8fafc;color:#1e293b;text-align:center;padding:20px;box-sizing:border-box}.card{background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:36px 28px;max-width:460px;box-shadow:0 10px 25px -5px rgba(0,0,0,0.08)}.icon{font-size:36px;margin-bottom:12px}h2{font-size:20px;font-weight:700;color:#0f172a;margin:0 0 10px}p{font-size:14px;line-height:1.6;color:#64748b;margin:0}</style></head><body><div class="card"><div class="icon">⚠️</div><h2>Subscription Expired</h2><p>Your plan has expired. Please renew your subscription. Contact SS Aqua Flow.</p></div></body></html>';
            return response($expiredHtml, 403)->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $html = $this->billingService->renderInvoiceHtml($invoice);

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Get the next lowest available invoice number.
     */
    public function nextNumber(Request $request): JsonResponse
    {
        $companyId = $request->user()?->company_id ?? 1;
        $prefix = $request->query('prefix');
        $nextNumber = $this->billingService->getLowestAvailableInvoiceNumber($companyId, $prefix);

        return response()->json([
            'status' => 'success',
            'data' => [
                'next_invoice_no' => $nextNumber,
            ],
        ]);
    }

    /**
     * Validate whether an invoice number is available.
     */
    public function validateNumber(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_no' => ['required', 'string', 'max:50'],
            'exclude_id' => ['nullable', 'integer'],
        ]);

        $companyId = $request->user()?->company_id ?? 1;
        $invoiceNo = trim($request->input('invoice_no'));
        $excludeId = $request->input('exclude_id') ? (int) $request->input('exclude_id') : null;

        $isAvailable = $this->billingService->isInvoiceNumberAvailable($companyId, $invoiceNo, $excludeId);
        $suggested = $isAvailable ? null : $this->billingService->getLowestAvailableInvoiceNumber($companyId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'is_available' => $isAvailable,
                'available' => $isAvailable,
                'invoice_no' => $invoiceNo,
                'suggested_invoice_no' => $suggested,
                'message' => $isAvailable ? 'Invoice number is available.' : "Invoice number '{$invoiceNo}' is already in use.",
            ],
        ]);
    }

    /**
     * Update an invoice number with audit tracking.
     */
    public function updateNumber(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        $request->validate([
            'invoice_no' => ['required', 'string', 'max:50'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $this->billingService->updateInvoiceNumber(
            $invoice,
            $request->input('invoice_no'),
            $request->input('reason'),
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice number updated successfully.',
            'data' => new InvoiceResource($updated->load(['dealer', 'items', 'creator'])),
        ]);
    }

    /**
     * Permanently delete an invoice to release its number.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        $invoiceNo = $invoice->invoice_no;
        $this->billingService->deleteInvoice($invoice, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => "Invoice '{$invoiceNo}' deleted successfully. The number is now released and reusable.",
        ]);
    }
}
