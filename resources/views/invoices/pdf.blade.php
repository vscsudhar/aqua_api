<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice - {{ $invoice->invoice_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 12mm 15mm 12mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.35;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
        }
        .invoice-title {
            font-size: 20px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: right;
        }
        .sub-header-title {
            font-size: 9px;
            color: #64748b;
            text-align: right;
            margin-top: 2px;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #0284c7;
        }
        .company-sub {
            font-size: 9.5px;
            color: #475569;
            margin-top: 2px;
        }
        .divider {
            border-top: 1.5px solid #0284c7;
            margin: 8px 0;
        }
        .box-table {
            border: 1px solid #cbd5e1;
            margin-bottom: 12px;
        }
        .box-table td {
            padding: 6px 8px;
            vertical-align: top;
            border-right: 1px solid #cbd5e1;
        }
        .box-table td:last-child {
            border-right: none;
        }
        .box-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #0284c7;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .items-table {
            border: 1px solid #cbd5e1;
            margin-bottom: 10px;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 4px;
            border-bottom: 1px solid #cbd5e1;
            border-right: 1px solid #e2e8f0;
            text-align: left;
        }
        .items-table th.text-right, .items-table td.text-right {
            text-align: right;
        }
        .items-table th.text-center, .items-table td.text-center {
            text-align: center;
        }
        .items-table td {
            padding: 6px 4px;
            border-bottom: 1px solid #f1f5f9;
            border-right: 1px solid #e2e8f0;
            font-size: 10px;
        }
        .items-table tr:last-child td {
            border-bottom: 1px solid #cbd5e1;
        }
        .totals-table td {
            padding: 3px 6px;
            font-size: 10px;
        }
        .totals-table .grand-row td {
            border-top: 1.5px solid #0284c7;
            border-bottom: 1.5px solid #0284c7;
            font-size: 12px;
            font-weight: bold;
            color: #0284c7;
            padding: 6px 6px;
        }
        .amount-in-words {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 10.5px;
            font-weight: bold;
            margin-bottom: 12px;
        }
        .bank-details {
            font-size: 9.5px;
            color: #334155;
            line-height: 1.4;
        }
        .footer-terms {
            font-size: 8.5px;
            color: #64748b;
            line-height: 1.35;
        }
        .signature-box {
            text-align: right;
            padding-top: 25px;
            font-size: 9.5px;
        }
        .cancelled-watermark {
            position: fixed;
            top: 35%;
            left: 20%;
            font-size: 65px;
            color: rgba(239, 68, 68, 0.18);
            font-weight: bold;
            transform: rotate(-30deg);
            z-index: 1000;
        }
    </style>
</head>
<body>
    @if($invoice->status === 'cancelled')
        <div class="cancelled-watermark">CANCELLED</div>
    @elseif($invoice->status === 'draft')
        <div class="cancelled-watermark" style="color: rgba(148, 163, 184, 0.22); left: 30%;">DRAFT</div>
    @endif

    <!-- Top Header -->
    <table class="header-table">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                @if(!empty($logoBase64))
                    <div style="margin-bottom: 8px;">
                        <img src="{{ $logoBase64 }}" style="max-height: 55px; max-width: 170px; object-fit: contain;" alt="Company Logo" />
                    </div>
                @endif
                <div class="company-name">{{ $settings->company_name }}</div>
                <div class="company-sub">{{ $settings->tagline }}</div>
                <div class="company-sub" style="margin-top: 4px;">{{ $settings->address }}</div>
                <div class="company-sub"><strong>GSTIN:</strong> {{ $settings->gstin }} &nbsp;|&nbsp; <strong>PAN:</strong> {{ $settings->pan }}</div>
                <div class="company-sub"><strong>Phone:</strong> {{ $settings->phone }} &nbsp;|&nbsp; <strong>Email:</strong> {{ $settings->email }}</div>
            </td>
            <td style="width: 45%; text-align: right;">
                <div class="invoice-title">Tax Invoice</div>
                <div class="sub-header-title">(ORIGINAL FOR RECIPIENT)</div>
                <table style="margin-top: 8px; width: 100%;">
                    <tr>
                        <td style="text-align: right; padding: 2px 0;"><strong>Invoice No:</strong></td>
                        <td style="text-align: right; padding: 2px 0; width: 130px; font-weight: bold; color: #0284c7;">{{ $invoice->invoice_no }}</td>
                    </tr>
                    <tr>
                        <td style="text-align: right; padding: 2px 0;"><strong>Invoice Date:</strong></td>
                        <td style="text-align: right; padding: 2px 0;">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-M-Y') }}</td>
                    </tr>
                    @if($invoice->due_date)
                    <tr>
                        <td style="text-align: right; padding: 2px 0;"><strong>Due Date:</strong></td>
                        <td style="text-align: right; padding: 2px 0;">{{ \Carbon\Carbon::parse($invoice->due_date)->format('d-M-Y') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="text-align: right; padding: 2px 0;"><strong>Place of Supply:</strong></td>
                        <td style="text-align: right; padding: 2px 0;">{{ $invoice->place_of_supply }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Bill To / Ship To / Supply Meta -->
    <table class="box-table">
        <tr>
            <td style="width: 60%;">
                <div class="box-title">Bill To (Customer / Dealer)</div>
                <div style="font-size: 12px; font-weight: bold; color: #0f172a;">{{ $invoice->customer_name }}</div>
                @if($invoice->customer_address)
                    <div style="margin-top: 2px; color: #475569;">{{ $invoice->customer_address }}</div>
                @endif
                <div style="margin-top: 3px;">
                    <strong>Phone:</strong> {{ $invoice->customer_phone ?? 'N/A' }}
                    @if($invoice->customer_email)
                        &nbsp;|&nbsp; <strong>Email:</strong> {{ $invoice->customer_email }}
                    @endif
                </div>
                <div style="margin-top: 2px;">
                    <strong>GSTIN:</strong> {{ $invoice->customer_gstin ? $invoice->customer_gstin : 'Unregistered (Consumer/Dealer)' }}
                </div>
            </td>
            <td style="width: 40%;">
                <div class="box-title">Dispatch & Tax Attributes</div>
                <div><strong>State / Code:</strong> {{ $invoice->place_of_supply }}</div>
                <div><strong>Tax Category:</strong> {{ $invoice->is_interstate ? 'Inter-State (IGST Applicable)' : 'Intra-State (CGST + SGST Applicable)' }}</div>
                @if($invoice->dealer_supply_id)
                    <div style="margin-top: 2px; color: #0284c7;"><strong>Linked Supply Ref:</strong> {{ $invoice->supply?->supply_no ?? '#' . $invoice->dealer_supply_id }}</div>
                @endif
                <div style="margin-top: 2px;"><strong>Reverse Charge:</strong> No</div>
                <div><strong>Status:</strong> <span style="text-transform: uppercase; font-weight: bold;">{{ $invoice->status }}</span></div>
            </td>
        </tr>
    </table>

    <!-- Line Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">#</th>
                <th style="width: 200px;">Description of Goods</th>
                <th style="width: 45px;" class="text-center">HSN</th>
                <th style="width: 35px;" class="text-right">Qty</th>
                <th style="width: 40px;" class="text-center">Unit</th>
                <th style="width: 50px;" class="text-right">Rate (₹)</th>
                <th style="width: 55px;" class="text-right">Taxable (₹)</th>
                @if($invoice->is_interstate)
                    <th style="width: 55px;" class="text-right">IGST (₹)</th>
                @else
                    <th style="width: 50px;" class="text-right">CGST (₹)</th>
                    <th style="width: 50px;" class="text-right">SGST (₹)</th>
                @endif
                <th style="width: 65px;" class="text-right">Total (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $idx => $item)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>
                    <strong>{{ $item->item_description }}</strong>
                    @if($item->notes)
                        <div style="font-size: 8.5px; color: #64748b;">{{ $item->notes }}</div>
                    @endif
                </td>
                <td class="text-center">{{ $item->hsn_sac }}</td>
                <td class="text-right">{{ number_format($item->quantity, 0) }}</td>
                <td class="text-center">{{ $item->unit }}</td>
                <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format($item->taxable_amount, 2) }}</td>
                @if($invoice->is_interstate)
                    <td class="text-right">
                        {{ number_format($item->igst_amount, 2) }}
                        <div style="font-size: 8px; color: #64748b;">{{ number_format($item->igst_rate, 0) }}%</div>
                    </td>
                @else
                    <td class="text-right">
                        {{ number_format($item->cgst_amount, 2) }}
                        <div style="font-size: 8px; color: #64748b;">{{ number_format($item->cgst_rate, 1) }}%</div>
                    </td>
                    <td class="text-right">
                        {{ number_format($item->sgst_amount, 2) }}
                        <div style="font-size: 8px; color: #64748b;">{{ number_format($item->sgst_rate, 1) }}%</div>
                    </td>
                @endif
                <td class="text-right" style="font-weight: bold;">{{ number_format($item->line_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Amount in Words & Totals Summary -->
    <table style="width: 100%; margin-bottom: 8px;">
        <tr>
            <td style="width: 58%; vertical-align: top;">
                <div class="amount-in-words">
                    <span style="color: #64748b; font-size: 9px; text-transform: uppercase;">Invoice Amount in Words:</span><br>
                    {{ $amountInWords }}
                </div>

                <!-- Bank & Payment Details -->
                <div style="border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 8px; background-color: #fafafa;">
                    <div style="font-size: 9px; font-weight: bold; color: #0284c7; text-transform: uppercase; margin-bottom: 2px;">Bank Remittance / UPI Payment</div>
                    <table class="bank-details" style="width: 100%;">
                        <tr>
                            <td style="width: 75px;"><strong>Bank Name:</strong></td>
                            <td>{{ $settings->bank_name }}</td>
                            <td style="width: 70px;"><strong>Branch:</strong></td>
                            <td>{{ $settings->bank_branch }}</td>
                        </tr>
                        <tr>
                            <td><strong>A/C No:</strong></td>
                            <td style="font-weight: bold; color: #0f172a;">{{ $settings->account_number }}</td>
                            <td><strong>IFSC Code:</strong></td>
                            <td style="font-weight: bold;">{{ $settings->ifsc_code }}</td>
                        </tr>
                        <tr>
                            <td><strong>UPI ID:</strong></td>
                            <td colspan="3" style="color: #0284c7; font-weight: bold;">{{ $settings->upi_id }}</td>
                        </tr>
                    </table>
                </div>
            </td>

            <!-- Financial Totals Block -->
            <td style="width: 42%; vertical-align: top; padding-left: 12px;">
                <table class="totals-table">
                    <tr>
                        <td style="width: 60%;">Subtotal (Base Value):</td>
                        <td style="text-align: right; width: 40%; font-weight: 500;">₹ {{ number_format($invoice->subtotal, 2) }}</td>
                    </tr>
                    @if($invoice->discount_total > 0)
                    <tr>
                        <td style="color: #16a34a;">Less: Discount:</td>
                        <td style="text-align: right; color: #16a34a;">- ₹ {{ number_format($invoice->discount_total, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td><strong>Taxable Value:</strong></td>
                        <td style="text-align: right; font-weight: bold;">₹ {{ number_format($invoice->taxable_amount, 2) }}</td>
                    </tr>
                    @if($invoice->is_interstate)
                    <tr>
                        <td>Integrated GST (IGST):</td>
                        <td style="text-align: right;">₹ {{ number_format($invoice->igst_amount, 2) }}</td>
                    </tr>
                    @else
                    <tr>
                        <td>Central GST (CGST):</td>
                        <td style="text-align: right;">₹ {{ number_format($invoice->cgst_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td>State GST (SGST):</td>
                        <td style="text-align: right;">₹ {{ number_format($invoice->sgst_amount, 2) }}</td>
                    </tr>
                    @endif
                    @if($invoice->round_off != 0)
                    <tr>
                        <td>Round Off:</td>
                        <td style="text-align: right;">{{ $invoice->round_off > 0 ? '+ ' : '' }}₹ {{ number_format($invoice->round_off, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="grand-row">
                        <td>Grand Total:</td>
                        <td style="text-align: right;">₹ {{ number_format($invoice->grand_total, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="color: #16a34a;">Amount Received / Paid:</td>
                        <td style="text-align: right; color: #16a34a; font-weight: 600;">₹ {{ number_format($invoice->paid_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="color: #b91c1c;"><strong>Balance Due:</strong></td>
                        <td style="text-align: right; color: #b91c1c; font-weight: bold; font-size: 11px;">₹ {{ number_format($invoice->balance_due, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Terms & Signature Footer -->
    <table style="width: 100%; margin-top: 10px; border-top: 1px solid #cbd5e1; padding-top: 8px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div style="font-size: 9px; font-weight: bold; color: #0284c7; text-transform: uppercase; margin-bottom: 3px;">Terms & Conditions:</div>
                <div class="footer-terms">{!! nl2br(e($invoice->terms ?? $settings->invoice_footer_notes)) !!}</div>
            </td>
            <td style="width: 40%; vertical-align: bottom; text-align: right;">
                <div style="font-size: 9.5px; font-weight: bold; color: #0f172a;">For {{ $settings->company_name }}</div>
                <div class="signature-box">Authorized Signatory</div>
            </td>
        </tr>
    </table>
</body>
</html>
