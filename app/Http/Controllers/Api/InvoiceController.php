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
        $query = Invoice::with(['dealer', 'supply', 'items', 'creator'])
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
            'dealer_id' => ['nullable', 'exists:dealers,id'],
            'dealer_supply_id' => ['nullable', 'exists:dealer_supplies,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_address' => ['nullable', 'string'],
            'customer_gstin' => ['nullable', 'string', 'max:50'],
            'place_of_supply' => ['nullable', 'string', 'max:100'],
            'is_interstate' => ['nullable', 'boolean'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:draft,finalized'],
            'is_final' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
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
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
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
        $invoice = Invoice::with(['dealer', 'supply', 'items.product', 'payments.creator', 'creditNotes.creator', 'creator', 'finalizer', 'canceller'])
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
            'dealer_id' => ['nullable', 'exists:dealers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_address' => ['nullable', 'string'],
            'customer_gstin' => ['nullable', 'string', 'max:50'],
            'place_of_supply' => ['nullable', 'string', 'max:100'],
            'is_interstate' => ['nullable', 'boolean'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:draft,finalized'],
            'is_final' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.item_description' => ['required', 'string', 'max:255'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
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

        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $cancelled = $this->billingService->cancelInvoice(
            $invoice,
            $request->input('reason'),
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
     * Download or stream PDF invoice.
     */
    public function downloadPdf(int $id): Response
    {
        $invoice = Invoice::findOrFail($id);
        $pdf = $this->billingService->renderInvoicePdf($invoice);

        return $pdf->download("Invoice-{$invoice->invoice_no}.pdf");
    }

    /**
     * Print / preview PDF inline in browser.
     */
    public function printPdf(int $id): Response
    {
        $invoice = Invoice::findOrFail($id);
        $pdf = $this->billingService->renderInvoicePdf($invoice);

        return $pdf->stream("Invoice-{$invoice->invoice_no}.pdf");
    }
}
