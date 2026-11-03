<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    @if(!empty($isHtmlView))
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    @endif
    <title>Tax Invoice - {{ $invoice->invoice_no }}</title>
    <style>
        @if(!empty($isHtmlView))
        /* ========================================================
           Interactive Screen View Styling (Browser URL /invoices/{id})
           ======================================================== */
        *, *::before, *::after {
            box-sizing: border-box;
            -webkit-box-sizing: border-box;
        }
        html {
            background-color: #f1f5f9;
            overflow-x: hidden;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.35;
            background-color: #f1f5f9;
            padding: 16px 8px 36px 8px;
            display: block;
            margin: 0 auto;
            max-width: 100vw;
            overflow-x: hidden;
            -webkit-text-size-adjust: 100%;
        }
        .screen-toolbar {
            width: 100%;
            max-width: 820px;
            margin: 0 auto 14px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            box-sizing: border-box;
            flex-wrap: wrap;
            gap: 10px;
        }
        .toolbar-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .invoice-badge {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .status-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-finalized { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .status-draft { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .status-cancelled { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .btn-primary { background: #0284c7; color: #ffffff; }
        .btn-primary:hover { background: #0369a1; }
        .btn-secondary { background: #0f172a; color: #ffffff; }
        .btn-secondary:hover { background: #1e293b; }
        .btn-light { background: #f8fafc; color: #334155; border-color: #cbd5e1; }
        .btn-light:hover { background: #e2e8f0; }

        .page-container {
            width: 100%;
            max-width: 820px;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 28px 24px;
            box-sizing: border-box;
            position: relative;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .table-responsive-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 12px;
        }

        @media (max-width: 640px) {
            body { padding: 8px 4px 24px 4px; }
            .page-container { padding: 14px 10px; border-radius: 4px; min-height: auto; }
            .screen-toolbar { padding: 10px; flex-direction: column; align-items: stretch; gap: 8px; }
            .toolbar-actions { display: flex; flex-wrap: wrap; gap: 6px; }
            .btn { flex: 1 1 auto; justify-content: center; }
            .header-table td.header-left, .header-table td.header-right { display: block !important; width: 100% !important; text-align: left !important; }
            .header-table td.header-right { margin-top: 12px; }
            .invoice-title { text-align: left !important; font-size: 18px !important; }
            .sub-header-title { text-align: left !important; }
            .meta-table { margin-top: 6px !important; }
            .meta-table td { display: table-cell !important; }
            .box-table td.box-col { display: block !important; width: 100% !important; border-right: none !important; border-bottom: 1px solid #cbd5e1 !important; }
            .box-table td.box-col:last-child { border-bottom: none !important; }
            .summary-table td.summary-col { display: block !important; width: 100% !important; padding-left: 0 !important; padding-right: 0 !important; }
            .summary-table td.summary-col:last-child { margin-top: 14px; }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm 10mm 10mm 10mm;
            }
            body { background: #ffffff !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .no-print { display: none !important; }
            .page-container { width: 100% !important; min-height: 277mm !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: none !important; }
            .table-responsive-wrapper { overflow: visible !important; }
        }

        table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .invoice-title { font-size: 20px; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; text-align: right; margin: 0; }
        .sub-header-title { font-size: 8.5px; color: #64748b; text-align: right; margin-top: 2px; }
        .company-name { font-size: 16px; font-weight: bold; color: #0284c7; }
        .company-sub { font-size: 9.5px; color: #475569; margin-top: 2px; }
        .divider { border-top: 1.5px solid #0284c7; margin: 8px 0; }
        .box-table { border: 1px solid #94a3b8; border-collapse: collapse; margin-bottom: 8px; table-layout: fixed; }
        .box-table td { padding: 6px 8px; vertical-align: top; border-right: 1px solid #94a3b8; }
        .box-table td:last-child { border-right: none; }
        .box-title { font-size: 9px; font-weight: bold; color: #0284c7; text-transform: uppercase; margin-bottom: 4px; }

        .items-table { border: 1px solid #94a3b8; margin-bottom: 8px; table-layout: fixed; border-collapse: collapse; }
        .items-table th { background-color: #f1f5f9; color: #0f172a; font-size: 9px; font-weight: bold; text-transform: uppercase; padding: 6px 4px; border-bottom: 1.5px solid #94a3b8; border-right: 1px solid #94a3b8; text-align: left; }
        .items-table th:last-child { border-right: none; }
        .items-table th.text-right, .items-table td.text-right { text-align: right; }
        .items-table th.text-center, .items-table td.text-center { text-align: center; }
        .items-table td { padding: 5px 4px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #94a3b8; font-size: 9.5px; word-wrap: break-word; }
        .items-table td:last-child { border-right: none; }
        .items-table tr.empty-row td { height: 20px; padding: 0 4px; border-bottom: 1px solid #f1f5f9; border-right: 1px solid #94a3b8; }
        .items-table tr.empty-row td:last-child { border-right: none; }
        .items-table tr:last-child td { border-bottom: 1px solid #94a3b8; }

        .totals-table td { padding: 3px 2px; font-size: 9.5px; }
        .totals-table .grand-row td { border-top: 1.5px solid #0284c7; border-bottom: 1.5px solid #0284c7; font-size: 11.5px; font-weight: bold; color: #0284c7; padding: 5px 2px; }
        .amount-in-words { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 8px; font-size: 10px; font-weight: bold; margin-bottom: 10px; }
        .bank-details { font-size: 9px; color: #334155; }
        .footer-terms { font-size: 8.5px; color: #64748b; }
        .signature-box { text-align: right; padding-top: 26px; font-size: 9px; }
        .cancelled-watermark { position: fixed; top: 35%; left: 20%; font-size: 65px; color: rgba(239, 68, 68, 0.18); font-weight: bold; transform: rotate(-30deg); z-index: 1000; }

        @else
        /* ========================================================
           Strict PDF Print Engine Styling (Dompdf Output)
           ======================================================== */
        @page {
            size: A4 portrait;
            margin: 10mm 10mm 10mm 10mm;
        }
        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #1e293b;
            line-height: 1.35;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }
        .page-container {
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
        }
        .invoice-title {
            font-size: 16pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            text-align: right;
            margin: 0;
            padding: 0;
        }
        .sub-header-title {
            font-size: 7.5pt;
            color: #64748b;
            text-align: right;
            margin-top: 2pt;
        }
        .company-name {
            font-size: 13pt;
            font-weight: bold;
            color: #0284c7;
            line-height: 1.2;
        }
        .company-sub {
            font-size: 7.5pt;
            color: #475569;
            margin-top: 1pt;
        }
        .divider {
            border-top: 1.5pt solid #0284c7;
            margin: 6pt 0;
        }
        .box-table {
            border: 0.75pt solid #94a3b8;
            border-collapse: collapse;
            margin-bottom: 6pt;
            table-layout: fixed;
        }
        .box-table td {
            padding: 4pt 6pt;
            vertical-align: top;
            border-right: 0.75pt solid #94a3b8;
        }
        .box-table td:last-child {
            border-right: none;
        }
        .box-title {
            font-size: 7.5pt;
            font-weight: bold;
            color: #0284c7;
            text-transform: uppercase;
            margin-bottom: 2pt;
        }
        .items-table {
            border: 0.75pt solid #94a3b8;
            margin-bottom: 8pt;
            table-layout: fixed;
            border-collapse: collapse;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4.5pt 3pt;
            border-bottom: 1pt solid #94a3b8;
            border-right: 0.75pt solid #94a3b8;
            text-align: left;
        }
        .items-table th:last-child {
            border-right: none;
        }
        .items-table th.text-right, .items-table td.text-right {
            text-align: right;
        }
        .items-table th.text-center, .items-table td.text-center {
            text-align: center;
        }
        .items-table td {
            padding: 4.5pt 3pt;
            border-bottom: 0.5pt solid #cbd5e1;
            border-right: 0.75pt solid #94a3b8;
            font-size: 7.8pt;
            word-wrap: break-word;
        }
        .items-table td:last-child {
            border-right: none;
        }
        .items-table tr.empty-row td {
            height: 18pt;
            padding: 0 3pt;
            border-bottom: 0.5pt solid #f1f5f9;
            border-right: 0.75pt solid #94a3b8;
        }
        .items-table tr.empty-row td:last-child {
            border-right: none;
        }
        .items-table tr:last-child td {
            border-bottom: 1pt solid #94a3b8;
        }
        .summary-table td {
            vertical-align: top;
        }
        .totals-table td {
            padding: 2.5pt 2pt;
            font-size: 7.8pt;
        }
        .totals-table .grand-row td {
            border-top: 1.5pt solid #0284c7;
            border-bottom: 1.5pt solid #0284c7;
            font-size: 9.5pt;
            font-weight: bold;
            color: #0284c7;
            padding: 4pt 2pt;
        }
        .amount-in-words {
            background-color: #f8fafc;
            border: 0.75pt solid #e2e8f0;
            padding: 5pt 6pt;
            font-size: 7.5pt;
            font-weight: bold;
            margin-bottom: 6pt;
            line-height: 1.35;
        }
        .bank-details {
            font-size: 7pt;
            color: #334155;
            line-height: 1.35;
        }
        .footer-terms {
            font-size: 7pt;
            color: #64748b;
            line-height: 1.3;
        }
        .signature-box {
            text-align: right;
            padding-top: 26pt;
            font-size: 7.5pt;
        }
        .cancelled-watermark {
            position: fixed;
            top: 35%;
            left: 20%;
            font-size: 55pt;
            color: rgba(239, 68, 68, 0.18);
            font-weight: bold;
            transform: rotate(-30deg);
            z-index: 1000;
        }
        @endif
    </style>
</head>
<body>
    @if(!empty($isHtmlView))
    <div class="screen-toolbar no-print">
        <div class="toolbar-info">
            <span class="invoice-badge">Tax Invoice: {{ $invoice->invoice_no }}</span>
        </div>
        <div class="toolbar-actions">
            <button type="button" onclick="window.print()" class="btn btn-primary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Print / Save PDF
            </button>
            <a href="/invoices/{{ $invoice->id }}/pdf" target="_blank" class="btn btn-secondary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Download PDF
            </a>
            <button type="button" onclick="window.location.reload()" class="btn btn-light" title="Refresh live invoice data">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                Refresh
            </button>
        </div>
    </div>
    @endif

    <div class="page-container">
        @if($invoice->status === 'cancelled')
            <div class="cancelled-watermark">CANCELLED</div>
        @elseif($invoice->status === 'draft')
            <div class="cancelled-watermark" style="color: rgba(148, 163, 184, 0.22); left: 30%;">DRAFT</div>
        @endif

    <!-- Top Header -->
    <table class="header-table" style="width: 100%; table-layout: fixed; margin-bottom: 2pt;">
        <tr>
            <!-- Left Corner: Company Logo -->
            <td style="width: 18%; vertical-align: middle; text-align: left;">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" style="max-height: 50px; max-width: 105px; object-fit: contain;" alt="Company Logo" />
                @endif
            </td>

            <!-- Center of Page on Top: Company Name and Address -->
            <td style="width: 68%; vertical-align: top; text-align: center; padding: 0 4px;">
                <div class="company-name" style="font-size: 13pt; font-weight: bold; color: #0284c7; text-transform: uppercase; line-height: 1.15;">{{ $settings->company_name }}</div>
                @if($settings->tagline)
                    <div class="company-sub" style="font-size: 7.5pt; color: #475569; font-weight: 600; margin-top: 1px;">{{ $settings->tagline }}</div>
                @endif
                <div class="company-sub" style="font-size: 7pt; color: #334155; margin-top: 1px; line-height: 1.25;">{{ $settings->address }}</div>
            </td>

            <!-- Right Corner: Original Marker -->
            <td style="width: 14%; vertical-align: top; text-align: right;">
                <div style="font-size: 7.5pt; color: #64748b; font-weight: bold;">(Original)</div>
            </td>
        </tr>
    </table>

    <!-- Centered TAX INVOICE (Tight spacing, no separate box) -->
    <div style="text-align: center; font-size: 8.5pt; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin: 3pt 0 2pt 0; line-height: 1.2;">
        TAX INVOICE
    </div>

    <!-- 2 Boxes Only: Left Customer Box | Right Contact & FSSAI Details Box (Fully closed and complete) -->
    <table class="box-table" style="width: 100%; table-layout: fixed; border-collapse: collapse; border: 0.75pt solid #94a3b8; margin: 0 0 5pt 0;">
        <tr>
            <!-- Box 1 (Left): Customer Details -->
            <td class="box-col" style="width: 52%; vertical-align: top; padding: 3pt 5pt; border-right: 0.75pt solid #94a3b8;">
                <div style="font-size: 7.5pt; font-weight: bold; color: #0284c7; margin-bottom: 2pt;">TO : {{ strtoupper($invoice->customer_name) }}</div>
                @if($invoice->customer_address)
                    <div style="font-size: 7pt; color: #334155; line-height: 1.25; margin-bottom: 2pt;">{{ $invoice->customer_address }}</div>
                @endif
                @if($invoice->customer_phone)
                    <div style="font-size: 7pt; line-height: 1.25;"><strong>Phone :</strong> {{ $invoice->customer_phone }}</div>
                @endif
                @if($invoice->customer_gstin)
                    <div style="font-size: 7pt; line-height: 1.25; margin-top: 1pt;"><strong>GSTIN :</strong> {{ $invoice->customer_gstin }}</div>
                @endif
            </td>

            <!-- Box 2 (Right): Company Contact Details with FSSAI & Invoice Info -->
            <td class="box-col" style="width: 48%; vertical-align: top; padding: 3pt 5pt; border-right: none;">
                <table style="width: 100%; border-collapse: collapse; font-size: 7pt; line-height: 1.25;">
                    <tr>
                        <td style="width: 32%; padding: 0.5pt 0; font-weight: bold; color: #0f172a; border: none;">Invoice No</td>
                        <td style="width: 68%; padding: 0.5pt 0; font-weight: bold; color: #0284c7; border: none;">: {{ $invoice->invoice_no }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.5pt 0; font-weight: bold; color: #0f172a; border: none;">Invoice Date</td>
                        <td style="padding: 0.5pt 0; border: none;">: {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.5pt 0; font-weight: bold; color: #0f172a; border: none;">Phone</td>
                        <td style="padding: 0.5pt 0; border: none;">: {{ $settings->phone }}</td>
                    </tr>
                    @if($settings->email)
                    <tr>
                        <td style="padding: 0.5pt 0; font-weight: bold; color: #0f172a; border: none;">Email</td>
                        <td style="padding: 0.5pt 0; border: none;">: {{ $settings->email }}</td>
                    </tr>
                    @endif
                    @if(!empty($settings->fssai_no))
                    <tr>
                        <td style="padding: 0.5pt 0; font-weight: bold; color: #0284c7; border: none;">FSSAI NO</td>
                        <td style="padding: 0.5pt 0; font-weight: bold; color: #0284c7; border: none;">: {{ $settings->fssai_no }}</td>
                    </tr>
                    @endif
                    @if($settings->gstin)
                    <tr>
                        <td style="padding: 0.5pt 0; font-weight: bold; color: #0f172a; border: none;">GSTIN</td>
                        <td style="padding: 0.5pt 0; border: none;">: {{ $settings->gstin }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- Line Items Table -->
    @if(!empty($isHtmlView))
    <div class="table-responsive-wrapper">
    @endif
    <table class="items-table" style="width: 100%; table-layout: fixed;">
        <thead>
            <tr>
                @if($invoice->is_interstate)
                    <th style="width: 5%;" class="text-center">#</th>
                    <th style="width: 31%;">Description of Goods</th>
                    <th style="width: 8%;" class="text-center">HSN</th>
                    <th style="width: 7%;" class="text-right">Qty</th>
                    <th style="width: 7%;" class="text-center">Unit</th>
                    <th style="width: 9%;" class="text-right">Rate (₹)</th>
                    <th style="width: 11%;" class="text-right">Taxable (₹)</th>
                    <th style="width: 11%;" class="text-right">IGST (₹)</th>
                    <th style="width: 11%;" class="text-right">Total (₹)</th>
                @else
                    <th style="width: 5%;" class="text-center">#</th>
                    <th style="width: 31%;">Description of Goods</th>
                    <th style="width: 8%;" class="text-center">HSN</th>
                    <th style="width: 7%;" class="text-right">Qty</th>
                    <th style="width: 7%;" class="text-center">Unit</th>
                    <th style="width: 9%;" class="text-right">Rate (₹)</th>
                    <th style="width: 11%;" class="text-right">Taxable (₹)</th>
                    <th style="width: 6%;" class="text-right">CGST (₹)</th>
                    <th style="width: 6%;" class="text-right">SGST (₹)</th>
                    <th style="width: 10%;" class="text-right">Total (₹)</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $idx => $item)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>
                    <strong>{{ $item->item_description }}</strong>
                    @if($item->notes)
                        <div style="font-size: 7.5pt; color: #64748b;">{{ $item->notes }}</div>
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
                        <div style="font-size: 7pt; color: #64748b;">{{ number_format($item->igst_rate, 0) }}%</div>
                    </td>
                @else
                    <td class="text-right">
                        {{ number_format($item->cgst_amount, 2) }}
                        <div style="font-size: 7pt; color: #64748b;">{{ number_format($item->cgst_rate, 1) }}%</div>
                    </td>
                    <td class="text-right">
                        {{ number_format($item->sgst_amount, 2) }}
                        <div style="font-size: 7pt; color: #64748b;">{{ number_format($item->sgst_rate, 1) }}%</div>
                    </td>
                @endif
                <td class="text-right" style="font-weight: bold;">{{ number_format($item->line_total, 2) }}</td>
            </tr>
            @endforeach

            @php
                $emptyRows = max(0, 11 - count($invoice->items));
            @endphp
            @for($i = 0; $i < $emptyRows; $i++)
            <tr class="empty-row">
                <td class="text-center">&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                @if($invoice->is_interstate)
                    <td>&nbsp;</td>
                @else
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                @endif
                <td>&nbsp;</td>
            </tr>
            @endfor
        </tbody>
    </table>
    @if(!empty($isHtmlView))
    </div>
    @endif

    <!-- Amount in Words & Totals Summary -->
    <table class="summary-table" style="width: 100%; table-layout: fixed; margin-bottom: 8px;">
        <tr>
            <td class="summary-col" style="width: 54%; vertical-align: top; padding-right: 6px;">
                <div class="amount-in-words">
                    <span style="color: #64748b; font-size: 7pt; text-transform: uppercase;">Invoice Amount in Words:</span><br>
                    {{ $amountInWords }}
                </div>

                <!-- Bank & Payment Details -->
                <div style="border: 1px solid #e2e8f0; border-radius: 4px; padding: 5px 7px; background-color: #fafafa;">
                    <div style="font-size: 7.5pt; font-weight: bold; color: #0284c7; text-transform: uppercase; margin-bottom: 2px;">Bank Remittance / UPI Payment</div>
                    <table class="bank-details" style="width: 100%; table-layout: fixed;">
                        <tr>
                            <td style="width: 25%;"><strong>Bank:</strong> {{ $settings->bank_name }}</td>
                            <td style="width: 25%;"><strong>Branch:</strong> {{ $settings->bank_branch }}</td>
                            <td style="width: 25%;"><strong>A/C No:</strong> <span style="font-weight: bold; color: #0f172a;">{{ $settings->account_number }}</span></td>
                            <td style="width: 25%;"><strong>IFSC:</strong> <span style="font-weight: bold;">{{ $settings->ifsc_code }}</span></td>
                        </tr>
                        <tr>
                            <td colspan="4" style="padding-top: 2px;"><strong>UPI ID:</strong> <span style="color: #0284c7; font-weight: bold;">{{ $settings->upi_id }}</span></td>
                        </tr>
                    </table>
                </div>
            </td>

            <!-- Financial Totals Block -->
            <td class="summary-col" style="width: 46%; vertical-align: top; padding-left: 6px;">
                <table class="totals-table" style="width: 100%; table-layout: fixed;">
                    <tr>
                        <td style="width: 55%; text-align: left; padding: 2px;">Subtotal (Base Value):</td>
                        <td style="width: 45%; text-align: right; padding: 2px; font-weight: 500; white-space: nowrap;">₹ {{ number_format($invoice->subtotal, 2) }}</td>
                    </tr>
                    @if($invoice->discount_total > 0)
                    <tr>
                        <td style="width: 55%; text-align: left; padding: 2px; color: #16a34a;">Less: Discount:</td>
                        <td style="width: 45%; text-align: right; padding: 2px; color: #16a34a; white-space: nowrap;">- ₹ {{ number_format($invoice->discount_total, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="width: 55%; text-align: left; padding: 2px;"><strong>Taxable Value:</strong></td>
                        <td style="width: 45%; text-align: right; padding: 2px; font-weight: bold; white-space: nowrap;">₹ {{ number_format($invoice->taxable_amount, 2) }}</td>
                    </tr>
                    @if($invoice->is_interstate)
                    <tr>
                        <td style="width: 55%; text-align: left; padding: 2px;">Integrated GST (IGST):</td>
                        <td style="width: 45%; text-align: right; padding: 2px; white-space: nowrap;">₹ {{ number_format($invoice->igst_amount, 2) }}</td>
                    </tr>
                    @else
                    <tr>
                        <td style="width: 55%; text-align: left; padding: 2px;">Central GST (CGST):</td>
                        <td style="width: 45%; text-align: right; padding: 2px; white-space: nowrap;">₹ {{ number_format($invoice->cgst_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 55%; text-align: left; padding: 2px;">State GST (SGST):</td>
                        <td style="width: 45%; text-align: right; padding: 2px; white-space: nowrap;">₹ {{ number_format($invoice->sgst_amount, 2) }}</td>
                    </tr>
                    @endif
                    @if($invoice->round_off != 0)
                    <tr>
                        <td style="width: 55%; text-align: left; padding: 2px;">Round Off:</td>
                        <td style="width: 45%; text-align: right; padding: 2px; white-space: nowrap;">{{ $invoice->round_off > 0 ? '+ ' : '' }}₹ {{ number_format($invoice->round_off, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="grand-row">
                        <td style="width: 55%; text-align: left; padding: 3px 2px;">Grand Total:</td>
                        <td style="width: 45%; text-align: right; padding: 3px 2px; white-space: nowrap;">₹ {{ number_format($invoice->grand_total, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 55%; text-align: left; padding: 2px; color: #16a34a;">Amount Received / Paid:</td>
                        <td style="width: 45%; text-align: right; padding: 2px; color: #16a34a; font-weight: 600; white-space: nowrap;">₹ {{ number_format($invoice->paid_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 55%; text-align: left; padding: 2px; color: #b91c1c;"><strong>Balance Due:</strong></td>
                        <td style="width: 45%; text-align: right; padding: 2px; color: #b91c1c; font-weight: bold; font-size: 8.5pt; white-space: nowrap;">₹ {{ number_format($invoice->balance_due, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Terms & Signature Footer -->
    <table style="width: 100%; table-layout: fixed; margin-top: 10px; border-top: 1px solid #cbd5e1; padding-top: 8px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div style="font-size: 7.5pt; font-weight: bold; color: #0284c7; text-transform: uppercase; margin-bottom: 2px;">Declaration:</div>
                <div class="footer-terms">{!! nl2br(e($invoice->terms ?? ($settings->invoice_footer_notes ?? 'We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.'))) !!}</div>
            </td>
            <td style="width: 40%; vertical-align: bottom; text-align: right;">
                <div style="font-size: 8pt; font-weight: bold; color: #0f172a;">For {{ $settings->company_name }}</div>
                <div class="signature-box">Authorized Signatory</div>
            </td>
        </tr>
    </table>
    </div>
</body>
</html>