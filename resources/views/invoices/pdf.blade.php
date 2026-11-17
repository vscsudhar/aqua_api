@php
    if (!function_exists('formatInvoiceAmount')) {
        function formatInvoiceAmount($val) {
            $num = (float) ($val ?? 0);
            return round($num, 2) == round($num, 0)
                ? number_format($num, 0)
                : number_format($num, 2);
        }
    }

    $isWithoutGst = method_exists($invoice, 'isWithoutGst') ? $invoice->isWithoutGst() : (($invoice->invoice_type ?? '') === 'without_gst');
    $isIgst = !$isWithoutGst && ((bool) $invoice->is_interstate || (float)$invoice->igst_amount > 0);
    $isCgstSgst = !$isWithoutGst && !$isIgst;

    $allItems = $invoice->items;
    $totalItemCount = $allItems->count();

    // Chunking logic:
    // Single page if <= 8 items.
    // Multi-page if > 8 items: Page 1 gets 8 items; remaining items split so last page has <= 7 items.
    if ($totalItemCount <= 8) {
        $chunks = [$allItems];
    } else {
        $chunks = [];
        $chunks[] = $allItems->slice(0, 8);
        $rem = $allItems->slice(8);
        while ($rem->count() > 0) {
            if ($rem->count() <= 7) {
                $chunks[] = $rem;
                break;
            } elseif ($rem->count() <= 12) {
                $half = (int) ceil($rem->count() / 2);
                $chunks[] = $rem->slice(0, $half);
                $rem = $rem->slice($half);
            } else {
                $chunks[] = $rem->slice(0, 8);
                $rem = $rem->slice(8);
            }
        }
    }
    $totalPages = count($chunks);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    @if(!empty($isHtmlView))
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    @endif
    <title>{{ $isWithoutGst ? 'Estimate' : 'Tax Invoice' }} - {{ $invoice->invoice_no }}</title>
    <style>
        /* Embedded Unicode fonts supporting Indian Rupee symbol (₹) */
        @font-face {
            font-family: 'Noto Sans';
            font-style: normal;
            font-weight: normal;
            src: url('{{ str_replace('\\', '/', public_path('fonts/NotoSans-Regular.ttf')) }}') format('truetype');
        }
        @font-face {
            font-family: 'Noto Sans';
            font-style: normal;
            font-weight: bold;
            src: url('{{ str_replace('\\', '/', public_path('fonts/NotoSans-Bold.ttf')) }}') format('truetype');
        }

        /* Global Page Setup: Equal margins for A4 printing */
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 8mm 8mm;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            -webkit-box-sizing: border-box;
        }

        @if(!empty($isHtmlView))
        /* Interactive Screen View Styling */
        html {
            background-color: #f1f5f9;
        }
        body {
            font-family: 'Noto Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #0f172a;
            line-height: 1.35;
            background-color: #f1f5f9;
            padding: 16px 8px 36px 8px;
            display: block;
            margin: 0 auto;
            max-width: 100%;
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
            border: 1px solid #cbd5e1;
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
            margin: 0 auto 20px auto;
            background: #ffffff;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            border: 1.5px solid #1e293b;
            box-sizing: border-box;
            position: relative;
        }
        .table-responsive-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        @media (max-width: 640px) {
            body { padding: 8px 4px 24px 4px; }
            .screen-toolbar { padding: 10px; flex-direction: column; align-items: stretch; gap: 8px; }
            .toolbar-actions { display: flex; flex-wrap: wrap; gap: 6px; }
            .btn { flex: 1 1 auto; justify-content: center; }
        }

        @media print {
            html {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                overflow: visible !important;
            }
            body {
                background: #ffffff !important;
                margin: 0 auto !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .no-print {
                display: none !important;
            }
            .page-container {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                margin: 0 auto !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: 1pt solid #1e293b !important;
                box-sizing: border-box !important;
                position: relative !important;
                overflow: visible !important;
                page-break-inside: avoid !important;
            }
            .page-break {
                page-break-after: always !important;
            }
            .table-responsive-wrapper {
                width: 100% !important;
                overflow: visible !important;
            }
            table {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                table-layout: fixed !important;
            }
        }

        @else
        /* Strict PDF Print Engine Styling (Dompdf Output) */
        body {
            font-family: 'Noto Sans', 'DejaVu Sans', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #0f172a;
            line-height: 1.3;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }
        .page-container {
            margin: 0;
            padding: 0;
            background: #ffffff;
            border: 1pt solid #1e293b;
            box-sizing: border-box;
            page-break-inside: avoid;
        }
        .page-break {
            page-break-after: always;
        }
        @endif

        /* Shared Global Rules */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td, th {
            box-sizing: border-box;
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
    </style>
</head>
<body>
    @if(!empty($isHtmlView))
    <div class="screen-toolbar no-print">
        <div class="toolbar-info">
            <span class="invoice-badge">{{ $isWithoutGst ? 'Estimate' : 'Tax Invoice' }}: {{ $invoice->invoice_no }}</span>
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

    @foreach($chunks as $pageIndex => $chunk)
    @php
        $p = $pageIndex + 1;
        $isFirstPage = ($p === 1);
        $isLastPage = ($p === $totalPages);
    @endphp

    <div class="page-container @if(!$isLastPage) page-break @endif" style="@if(!$isLastPage) page-break-after: always; @endif">
        @if($invoice->status === 'cancelled')
            <div class="cancelled-watermark">CANCELLED</div>
        @elseif($invoice->status === 'draft')
            <div class="cancelled-watermark" style="color: rgba(148, 163, 184, 0.22); left: 30%;">DRAFT</div>
        @endif

        <!-- TOP HEADER: Logo (Left), Company Name & Address (Center), Copy Marker (Right) -->
        <div style="padding: 6pt 12pt 3pt 12pt;">
            <table style="width: 100%; table-layout: fixed; border-collapse: collapse;">
                <tr>
                    <!-- Left Corner: Logo -->
                    <td style="width: 20%; vertical-align: middle; text-align: left;">
                        @if(!empty($logoBase64))
                            <img src="{{ $logoBase64 }}" style="max-height: 60px; max-width: 120px; object-fit: contain;" alt="Logo" />
                        @endif
                    </td>

                    <!-- Center: Company Name & Address -->
                    <td style="width: 60%; vertical-align: top; text-align: center; padding: 1pt 6pt 0 6pt;">
                        <div style="font-size: 19pt; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.2; margin-bottom: 3pt;">
                            {{ $settings->company_name }}
                        </div>
                        @if($settings->address)
                            <div style="font-size: 10.5pt; color: #334155; line-height: 1.3; padding: 0 4pt;">
                                {{ $settings->address }}
                            </div>
                        @endif
                    </td>

                    <!-- Right Corner: Copy Marker -->
                    <td style="width: 20%; vertical-align: top; text-align: right; padding-top: 2pt;">
                        <div style="font-size: 9pt; color: #003366; font-weight: bold; border: 0.8pt solid #003366; padding: 2.5pt 5pt; border-radius: 2.5pt; display: inline-block;">
                            {{ strtoupper($copyType ?? ($isWithoutGst ? 'Original for Customer' : 'Original for Recipient')) }}
                        </div>
                    </td>
                </tr>
            </table>

            <!-- COMPANY CONTACT ROW: Left (GSTIN, FSSAI NO) | Right (Phone, Email) -->
            <table style="width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 5pt; font-size: 9.5pt; color: #1e293b; line-height: 1.35;">
                <tr>
                    <!-- Left: GSTIN & FSSAI NO -->
                    <td style="width: 50%; vertical-align: bottom; text-align: left;">
                        @if(!$isWithoutGst && !empty(trim($settings->gstin ?? '')))
                            <div style="font-size: 11.5pt; font-weight: bold; color: #0f172a; margin-bottom: 1pt;">
                                <strong>GSTIN :</strong> {{ $settings->gstin }}
                            </div>
                        @endif
                        @if(!empty(trim($settings->fssai_no ?? '')))
                            <div><strong>FSSAI NO :</strong> {{ $settings->fssai_no }}</div>
                        @endif
                    </td>

                    <!-- Right: Phone & Email -->
                    <td style="width: 50%; vertical-align: bottom; text-align: right;">
                        @if(!empty(trim($settings->phone ?? '')))
                            <div><strong>Phone :</strong> {{ $settings->phone }}</div>
                        @endif
                        @if(!empty(trim($settings->email ?? '')))
                            <div style="margin-top: 1pt;"><strong>Email :</strong> {{ $settings->email }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <!-- TAX INVOICE / ESTIMATE STRIP -->
        <div style="text-align: center; font-size: 10.5pt; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 1px; padding: 4pt 0; border-top: 1pt solid #1e293b; border-bottom: 1pt solid #1e293b; background: #ffffff;">
            {{ $isWithoutGst ? 'ESTIMATE' : 'TAX INVOICE' }}
        </div>

        @if($isFirstPage)
        <!-- 2-COLUMN BOX: Customer Details (Left) | Invoice Metadata (Right) -->
        <table style="width: 100%; table-layout: fixed; border-collapse: collapse; border-bottom: 1pt solid #1e293b;">
            <tr>
                <!-- Left Box: Customer Details -->
                <td style="width: 54%; vertical-align: top; padding: 5pt 12pt; border-right: 1pt solid #1e293b; font-size: 10pt; color: #1e293b; line-height: 1.4;">
                    <div style="font-weight: bold; color: #0f172a; font-size: 11.5pt; margin-bottom: 2pt;">
                        TO : {{ strtoupper(trim(preg_replace('/\s*\((?:COMP|DEALER|CODE)[^\)]*\)/i', '', $invoice->customer_name))) }}
                    </div>
                    @if($invoice->customer_address)
                        <div style="margin-bottom: 2pt; color: #334155; font-size: 10pt;">{{ $invoice->customer_address }}</div>
                    @endif
                    @if($invoice->place_of_supply)
                        <div style="margin-bottom: 2pt; font-weight: 500; font-size: 10pt;">{{ strtoupper($invoice->place_of_supply) }}</div>
                    @endif
                    @if($invoice->customer_phone)
                        <div><strong>Phone :</strong> {{ $invoice->customer_phone }}</div>
                    @endif
                    @if(!$isWithoutGst && $invoice->customer_gstin)
                        <div style="margin-top: 2pt; font-size: 11pt; font-weight: bold;"><strong>GSTIN :</strong> {{ $invoice->customer_gstin }}</div>
                    @endif
                </td>

                <!-- Right Box: Invoice Metadata -->
                <td style="width: 46%; vertical-align: top; padding: 5pt 12pt; font-size: 10pt; line-height: 1.45;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 10pt;">
                        <tr>
                            <td style="width: 38%; padding: 1.5pt 0; font-weight: bold; color: #0f172a;">{{ $isWithoutGst ? 'Estimate No' : 'Invoice No' }}</td>
                            <td style="width: 62%; padding: 1.5pt 0; font-weight: bold; color: #0f172a;">: {{ $invoice->invoice_no }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 1.5pt 0; font-weight: bold; color: #0f172a;">{{ $isWithoutGst ? 'Estimate Date' : 'Invoice Date' }}</td>
                            <td style="padding: 1.5pt 0;">: {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td>
                        </tr>
                        @if($invoice->due_date)
                        <tr>
                            <td style="padding: 1.5pt 0; font-weight: bold; color: #0f172a;">Due Date</td>
                            <td style="padding: 1.5pt 0;">: {{ \Carbon\Carbon::parse($invoice->due_date)->format('d-m-Y') }}</td>
                        </tr>
                        @endif
                        @if(!empty(trim($invoice->payment_mode ?? '')))
                        <tr>
                            <td style="padding: 1.5pt 0; font-weight: bold; color: #0f172a;">Payment Mode</td>
                            <td style="padding: 1.5pt 0; text-transform: uppercase;">: {{ $invoice->payment_mode }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
        @else
        <!-- Repeated Header Customer & Metadata (Page 2+) -->
        <table style="width: 100%; table-layout: fixed; border-collapse: collapse; border-bottom: 1pt solid #1e293b;">
            <tr>
                <td style="width: 54%; vertical-align: top; padding: 4pt 12pt; border-right: 1pt solid #1e293b; font-size: 9.5pt; color: #1e293b; line-height: 1.35;">
                    <div style="font-weight: bold; color: #0f172a; font-size: 10.5pt;">
                        TO : {{ strtoupper(trim(preg_replace('/\s*\((?:COMP|DEALER|CODE)[^\)]*\)/i', '', $invoice->customer_name))) }}
                    </div>
                </td>
                <td style="width: 46%; vertical-align: top; padding: 4pt 12pt; font-size: 9.5pt; line-height: 1.35;">
                    <span style="font-weight: bold; color: #0f172a;">{{ $isWithoutGst ? 'Estimate No' : 'Invoice No' }}:</span> {{ $invoice->invoice_no }} &nbsp;|&nbsp;
                    <span style="font-weight: bold; color: #0f172a;">Date:</span> {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}
                </td>
            </tr>
        </table>
        @endif

        <!-- LINE ITEMS TABLE -->
        @if(!empty($isHtmlView))
        <div class="table-responsive-wrapper">
        @endif
        <table style="width: 100%; table-layout: fixed; border-collapse: collapse; border-bottom: 1pt solid #1e293b; margin-top: 0;">
            <thead>
                <tr style="border-bottom: 1pt solid #1e293b; background: #ffffff;">
                    <th style="width: {{ $isWithoutGst ? '5%' : '4%' }}; padding: 4pt 2pt; font-size: 7.2pt; font-weight: bold; text-align: center; border-right: 1pt solid #1e293b;">S.No</th>
                    <th style="width: {{ $isWithoutGst ? '37%' : '23%' }}; padding: 4pt 4pt; font-size: 7.2pt; font-weight: bold; text-align: left; border-right: 1pt solid #1e293b;">Prod Name</th>
                    <th style="width: {{ $isWithoutGst ? '10%' : '8%' }}; padding: 4pt 2pt; font-size: 7.2pt; font-weight: bold; text-align: center; border-right: 1pt solid #1e293b;">HSN</th>
                    <th style="width: {{ $isWithoutGst ? '8%' : '6%' }}; padding: 4pt 2pt; font-size: 7.2pt; font-weight: bold; text-align: center; border-right: 1pt solid #1e293b;">Qty</th>
                    <th style="width: {{ $isWithoutGst ? '8%' : '6%' }}; padding: 4pt 2pt; font-size: 7.2pt; font-weight: bold; text-align: center; border-right: 1pt solid #1e293b;">UOM</th>
                    <th style="width: {{ $isWithoutGst ? '10%' : '8%' }}; padding: 4pt 2pt; font-size: 7.2pt; font-weight: bold; text-align: right; border-right: 1pt solid #1e293b;">Rate</th>
                    <th style="width: {{ $isWithoutGst ? '11%' : '8%' }}; padding: 4pt 2pt; font-size: 7.2pt; font-weight: bold; text-align: right; border-right: 1pt solid #1e293b;">Amount</th>
                    @if(!$isWithoutGst)
                    <th style="width: 8%; padding: 4pt 2pt; font-size: 7.2pt; font-weight: bold; text-align: right; border-right: 1pt solid #1e293b;">CGST</th>
                    <th style="width: 8%; padding: 4pt 2pt; font-size: 7.2pt; font-weight: bold; text-align: right; border-right: 1pt solid #1e293b;">SGST</th>
                    <th style="width: 8%; padding: 4pt 2pt; font-size: 7.2pt; font-weight: bold; text-align: right; border-right: 1pt solid #1e293b;">IGST</th>
                    @endif
                    <th style="width: {{ $isWithoutGst ? '11%' : '11%' }}; padding: 4pt 3pt; font-size: 7.2pt; font-weight: bold; text-align: right;">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($chunk as $localIdx => $item)
                @php
                    $globalIdx = $allItems->search(function ($it) use ($item) {
                        return $it->id === $item->id;
                    });
                    $rowIdx = $globalIdx !== false ? $globalIdx : $localIdx;

                    $itTaxRate = (float)($item->tax_rate ?? 0);
                    $itCgstRate = (float)($item->cgst_rate ?? 0);
                    $itSgstRate = (float)($item->sgst_rate ?? 0);
                    $itIgstRate = (float)($item->igst_rate ?? 0);

                    if (!$isIgst) {
                        if ($itCgstRate <= 0 && $itTaxRate > 0) {
                            $itCgstRate = round($itTaxRate / 2, 2);
                        }
                        if ($itSgstRate <= 0 && $itTaxRate > 0) {
                            $itSgstRate = round($itTaxRate / 2, 2);
                        }
                    } else {
                        if ($itIgstRate <= 0 && $itTaxRate > 0) {
                            $itIgstRate = $itTaxRate;
                        }
                    }

                    $cgstRateStr = $itCgstRate == round($itCgstRate, 0) ? number_format($itCgstRate, 0) : number_format($itCgstRate, 1);
                    $sgstRateStr = $itSgstRate == round($itSgstRate, 0) ? number_format($itSgstRate, 0) : number_format($itSgstRate, 1);
                    $igstRateStr = $itIgstRate == round($itIgstRate, 0) ? number_format($itIgstRate, 0) : number_format($itIgstRate, 1);
                @endphp
                <tr style="border-bottom: 0.5pt solid #cbd5e1; font-size: 7.2pt;">
                    <td style="padding: 3.5pt 2pt; text-align: center; border-right: 1pt solid #1e293b;">{{ $rowIdx + 1 }}</td>
                    <td style="padding: 3.5pt 4pt; text-align: left; border-right: 1pt solid #1e293b;">
                        <strong>{{ $item->item_description }}</strong>
                        @if($item->notes)
                            <div style="font-size: 6.5pt; color: #64748b;">{{ $item->notes }}</div>
                        @endif
                    </td>
                    <td style="padding: 3.5pt 2pt; text-align: center; border-right: 1pt solid #1e293b;">{{ $item->hsn_sac }}</td>
                    <td style="padding: 3.5pt 2pt; text-align: center; border-right: 1pt solid #1e293b;">{{ number_format($item->quantity, 0) }}</td>
                    <td style="padding: 3.5pt 2pt; text-align: center; border-right: 1pt solid #1e293b;">{{ $item->unit ?? 'Pcs' }}</td>
                    <td style="padding: 3.5pt 2pt; text-align: right; border-right: 1pt solid #1e293b;">{{ formatInvoiceAmount($item->unit_price) }}</td>
                    <td style="padding: 3.5pt 2pt; text-align: right; border-right: 1pt solid #1e293b;">{{ formatInvoiceAmount($item->taxable_amount) }}</td>
                    @if(!$isWithoutGst)
                        <!-- CGST Column -->
                        <td style="padding: 2.5pt 2pt; text-align: right; border-right: 1pt solid #1e293b; line-height: 1.15;">
                            @if(!$isIgst)
                                <div style="font-weight: bold;">₹{{ formatInvoiceAmount($item->cgst_amount) }}</div>
                                <div style="font-size: 6.2pt; color: #475569;">({{ $cgstRateStr }}%)</div>
                            @else
                                -
                            @endif
                        </td>
                        <!-- SGST Column -->
                        <td style="padding: 2.5pt 2pt; text-align: right; border-right: 1pt solid #1e293b; line-height: 1.15;">
                            @if(!$isIgst)
                                <div style="font-weight: bold;">₹{{ formatInvoiceAmount($item->sgst_amount) }}</div>
                                <div style="font-size: 6.2pt; color: #475569;">({{ $sgstRateStr }}%)</div>
                            @else
                                -
                            @endif
                        </td>
                        <!-- IGST Column -->
                        <td style="padding: 2.5pt 2pt; text-align: right; border-right: 1pt solid #1e293b; line-height: 1.15;">
                            @if($isIgst)
                                <div style="font-weight: bold;">₹{{ formatInvoiceAmount($item->igst_amount) }}</div>
                                <div style="font-size: 6.2pt; color: #475569;">({{ $igstRateStr }}%)</div>
                            @else
                                -
                            @endif
                        </td>
                    @endif
                    <td style="padding: 3.5pt 3pt; text-align: right; font-weight: bold;">{{ formatInvoiceAmount($item->line_total) }}</td>
                </tr>
                @endforeach

                @if($totalPages === 1)
                <!-- Filler Rows only when 1 single page to keep table proportion without overflow -->
                @php
                    $emptyRows = max(0, 2 - count($chunk));
                @endphp
                @for($i = 0; $i < $emptyRows; $i++)
                <tr style="height: 12pt;">
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    @if(!$isWithoutGst)
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    @endif
                    <td>&nbsp;</td>
                </tr>
                @endfor

                <!-- Summary Row inside Items Table for Single Page -->
                @php
                    $totalQty = $allItems->sum('quantity');
                    $totalLineSum = $allItems->sum('line_total');
                @endphp
                <tr style="border-top: 1pt solid #1e293b; font-size: 7.2pt; font-weight: bold;">
                    <td style="padding: 4pt 2pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4pt 4pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4pt 2pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4pt 2pt; text-align: center; border-right: 1pt solid #1e293b;">{{ number_format($totalQty, 0) }}</td>
                    <td style="padding: 4pt 2pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4pt 2pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4pt 2pt; text-align: right; border-right: 1pt solid #1e293b;">{{ formatInvoiceAmount($invoice->taxable_amount) }}</td>
                    @if(!$isWithoutGst)
                    <td style="padding: 4pt 2pt; text-align: right; border-right: 1pt solid #1e293b;">{{ !$isIgst && $invoice->cgst_amount > 0 ? '₹' . formatInvoiceAmount($invoice->cgst_amount) : (!$isIgst ? '₹0' : '-') }}</td>
                    <td style="padding: 4pt 2pt; text-align: right; border-right: 1pt solid #1e293b;">{{ !$isIgst && $invoice->sgst_amount > 0 ? '₹' . formatInvoiceAmount($invoice->sgst_amount) : (!$isIgst ? '₹0' : '-') }}</td>
                    <td style="padding: 4pt 2pt; text-align: right; border-right: 1pt solid #1e293b;">{{ $isIgst && $invoice->igst_amount > 0 ? '₹' . formatInvoiceAmount($invoice->igst_amount) : ($isIgst ? '₹0' : '-') }}</td>
                    @endif
                    <td style="padding: 4pt 3pt; text-align: right;">{{ formatInvoiceAmount($totalLineSum) }}</td>
                </tr>
                @else
                <!-- Page Subtotal Row for Multi-Page Invoice -->
                @php
                    $chunkQty = $chunk->sum('quantity');
                    $chunkTaxable = $chunk->sum('taxable_amount');
                    $chunkCgst = $chunk->sum('cgst_amount');
                    $chunkSgst = $chunk->sum('sgst_amount');
                    $chunkIgst = $chunk->sum('igst_amount');
                    $chunkTotal = $chunk->sum('line_total');
                @endphp
                <tr style="border-top: 1pt solid #1e293b; font-size: 7.2pt; font-weight: bold; background: #f8fafc;">
                    <td style="padding: 4pt 2pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4pt 4pt; border-right: 1pt solid #1e293b; color: #0284c7;">Page {{ $p }} Subtotal</td>
                    <td style="padding: 4pt 2pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4pt 2pt; text-align: center; border-right: 1pt solid #1e293b;">{{ number_format($chunkQty, 0) }}</td>
                    <td style="padding: 4pt 2pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4pt 2pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4pt 2pt; text-align: right; border-right: 1pt solid #1e293b;">{{ formatInvoiceAmount($chunkTaxable) }}</td>
                    @if(!$isWithoutGst)
                    <td style="padding: 4pt 2pt; text-align: right; border-right: 1pt solid #1e293b;">{{ !$isIgst ? '₹' . formatInvoiceAmount($chunkCgst) : '-' }}</td>
                    <td style="padding: 4pt 2pt; text-align: right; border-right: 1pt solid #1e293b;">{{ !$isIgst ? '₹' . formatInvoiceAmount($chunkSgst) : '-' }}</td>
                    <td style="padding: 4pt 2pt; text-align: right; border-right: 1pt solid #1e293b;">{{ $isIgst ? '₹' . formatInvoiceAmount($chunkIgst) : '-' }}</td>
                    @endif
                    <td style="padding: 4pt 3pt; text-align: right; color: #0284c7;">{{ formatInvoiceAmount($chunkTotal) }}</td>
                </tr>
                @endif
            </tbody>
        </table>
        @if(!empty($isHtmlView))
        </div>
        @endif

        @if($isLastPage)
        <!-- BOTTOM 2-COLUMN SECTION: Bank Details (Left) | Final Consolidated Totals & Words (Right) -->
        <table style="width: 100%; table-layout: fixed; border-collapse: collapse; border-bottom: 1pt solid #1e293b;">
            <tr>
                <!-- Left: Bank Details & Notes -->
                <td style="width: 54%; vertical-align: top; padding: 6pt 12pt; border-right: 1pt solid #1e293b; font-size: 9.5pt; line-height: 1.35;">
                    <div style="font-weight: bold; font-size: 10.5pt; color: #0f172a; margin-bottom: 3pt;">Bank Details</div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 9.5pt;">
                        <tr>
                            <td style="width: 25%; padding: 1.5pt 0; color: #334155;">Name</td>
                            <td style="width: 75%; padding: 1.5pt 0; font-weight: 600; color: #0f172a;">: {{ $settings->company_name }}</td>
                        </tr>
                        @if($settings->account_number)
                        <tr>
                            <td style="padding: 1.5pt 0; color: #334155;">A/C No</td>
                            <td style="padding: 1.5pt 0; font-weight: bold; color: #0f172a;">: {{ $settings->account_number }}</td>
                        </tr>
                        @endif
                        @if($settings->bank_name)
                        <tr>
                            <td style="padding: 1.5pt 0; color: #334155;">Bank</td>
                            <td style="padding: 1.5pt 0;">: {{ $settings->bank_name }}</td>
                        </tr>
                        @endif
                        @if($settings->ifsc_code)
                        <tr>
                            <td style="padding: 1.5pt 0; color: #334155;">IFSC Code</td>
                            <td style="padding: 1.5pt 0; font-weight: bold; color: #0f172a;">: {{ $settings->ifsc_code }}</td>
                        </tr>
                        @endif
                        @if($settings->bank_branch)
                        <tr>
                            <td style="padding: 1.5pt 0; color: #334155;">Branch</td>
                            <td style="padding: 1.5pt 0;">: {{ $settings->bank_branch }}</td>
                        </tr>
                        @endif
                    </table>

                    @if(!empty(trim($invoice->notes ?? '')))
                    <div style="margin-top: 4pt; font-size: 9pt; color: #475569;">
                        <strong>Notes:</strong> {{ $invoice->notes }}
                    </div>
                    @endif

                    @php
                        $disclaimerText = !empty(trim($invoice->terms ?? '')) 
                            ? trim($invoice->terms) 
                            : (!empty(trim($settings->invoice_footer_notes ?? '')) 
                                ? trim($settings->invoice_footer_notes) 
                                : '');
                    @endphp
                    @if(!empty($disclaimerText))
                    <div style="margin-top: 4pt; padding: 3pt 5pt; background-color: #f8fafc; border: 0.5pt solid #cbd5e1; border-radius: 3pt;">
                        <div style="font-size: 9pt; font-weight: bold; color: #0284c7; text-transform: uppercase; margin-bottom: 1.5pt;">Declaration / Disclaimer:</div>
                        <div style="font-size: 8.5pt; color: #334155; line-height: 1.3; white-space: pre-line;">{!! nl2br(e($disclaimerText)) !!}</div>
                    </div>
                    @endif
                </td>

                <!-- Right: Final Consolidated Net Amount & Words -->
                <td style="width: 46%; vertical-align: top; padding: 6pt 12pt;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 10pt;">
                        <tr>
                            <td style="color: #334155; padding: 1.5pt 0;">Subtotal:</td>
                            <td style="text-align: right; font-weight: 500; padding: 1.5pt 0;">₹{{ formatInvoiceAmount($invoice->subtotal) }}</td>
                        </tr>
                        @if($invoice->discount_total > 0)
                        <tr>
                            <td style="color: #16a34a; padding: 1.5pt 0;">Discount:</td>
                            <td style="text-align: right; color: #16a34a; padding: 1.5pt 0;">- ₹{{ formatInvoiceAmount($invoice->discount_total) }}</td>
                        </tr>
                        @endif
                        @if(!$isWithoutGst)
                        <tr>
                            <td style="color: #334155; padding: 1.5pt 0; font-weight: 600;">Taxable Amount:</td>
                            <td style="text-align: right; font-weight: 600; padding: 1.5pt 0;">₹{{ formatInvoiceAmount($invoice->taxable_amount) }}</td>
                        </tr>
                        @if($isIgst || (float)$invoice->igst_amount > 0)
                        @php
                            $igstPct = (float)($invoice->taxable_amount > 0 ? round(($invoice->igst_amount / $invoice->taxable_amount) * 100, 1) : 0);
                            if ($igstPct <= 0 && $allItems->count() > 0) {
                                $igstPct = (float)($allItems->first()->igst_rate ?? $allItems->first()->tax_rate ?? 0);
                            }
                            $igstPctStr = $igstPct == round($igstPct, 0) ? number_format($igstPct, 0) : number_format($igstPct, 1);
                        @endphp
                        <tr>
                            <td style="color: #334155; padding: 1.5pt 0;">IGST ({{ $igstPctStr }}%):</td>
                            <td style="text-align: right; padding: 1.5pt 0;">₹{{ formatInvoiceAmount($invoice->igst_amount) }}</td>
                        </tr>
                        @endif
                        @if(!$isIgst || (float)$invoice->cgst_amount > 0 || (float)$invoice->sgst_amount > 0)
                        @php
                            $totalGstPct = (float)($invoice->taxable_amount > 0 ? round((($invoice->cgst_amount + $invoice->sgst_amount) / $invoice->taxable_amount) * 100, 1) : 0);
                            if ($totalGstPct <= 0 && $allItems->count() > 0) {
                                $totalGstPct = (float)($allItems->first()->tax_rate ?? 0);
                            }
                            $cgstPct = round($totalGstPct / 2, 1);
                            $sgstPct = round($totalGstPct / 2, 1);
                            $cgstPctStr = $cgstPct == round($cgstPct, 0) ? number_format($cgstPct, 0) : number_format($cgstPct, 1);
                            $sgstPctStr = $sgstPct == round($sgstPct, 0) ? number_format($sgstPct, 0) : number_format($sgstPct, 1);
                        @endphp
                        <tr>
                            <td style="color: #334155; padding: 1.5pt 0;">CGST ({{ $cgstPctStr }}%):</td>
                            <td style="text-align: right; padding: 1.5pt 0;">₹{{ formatInvoiceAmount($invoice->cgst_amount) }}</td>
                        </tr>
                        <tr>
                            <td style="color: #334155; padding: 1.5pt 0;">SGST ({{ $sgstPctStr }}%):</td>
                            <td style="text-align: right; padding: 1.5pt 0;">₹{{ formatInvoiceAmount($invoice->sgst_amount) }}</td>
                        </tr>
                        @endif
                        @endif
                        @if(abs($invoice->round_off) > 0.001)
                        <tr>
                            <td style="color: #0284c7; padding: 1.5pt 0;">Round Off:</td>
                            <td style="text-align: right; color: #0284c7; padding: 1.5pt 0;">+₹{{ formatInvoiceAmount(abs($invoice->round_off)) }}</td>
                        </tr>
                        @endif
                        <tr style="border-top: 0.5pt solid #cbd5e1;">
                            <td style="font-size: 12pt; font-weight: bold; color: #0284c7; padding: 3pt 0;">Grand Total</td>
                            <td style="font-size: 14.5pt; font-weight: bold; text-align: right; color: #0284c7; padding: 3pt 0;">₹{{ formatInvoiceAmount($invoice->grand_total) }}</td>
                        </tr>
                    </table>

                    <div style="font-size: 9.5pt; color: #334155; margin-top: 4pt; line-height: 1.3;">
                        <strong>In Words :</strong> {{ $amountInWords }}
                    </div>
                </td>
            </tr>
        </table>

        <!-- SIGNATURES -->
        <div style="padding: 6pt 12pt 4pt 12pt;">
            <!-- Company Sign-off Line -->
            <div style="text-align: right; font-size: 10pt; font-weight: bold; color: #0f172a; margin-bottom: 14pt;">
                for {{ $settings->company_name }}
            </div>

            <!-- Signatures Row -->
            <table style="width: 100%; table-layout: fixed; font-size: 9.5pt; color: #0f172a; margin-bottom: 6pt;">
                <tr>
                    <td style="width: 33%; text-align: left; font-weight: 600;">Receiver Signature</td>
                    <td style="width: 34%; text-align: center; font-weight: 600;">Checked by</td>
                    <td style="width: 33%; text-align: right; font-weight: 600;">Authorized Signature</td>
                </tr>
            </table>
        </div>
        @endif

        <!-- BOTTOM DISCLAIMER BAR (Consistent on Every Page) -->
        <div style="padding: 3pt 12pt 4pt 12pt; border-top: 0.5pt solid #cbd5e1;">
            <table style="width: 100%; table-layout: fixed; font-size: 8.5pt; color: #475569;">
                <tr>
                    <td style="width: 25%; text-align: left; font-weight: bold; color: #0f172a;">
                        Page {{ $p }} of {{ $totalPages }}
                    </td>
                    <td style="width: 50%; text-align: center; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">
                        THANK YOU! VISIT AGAIN
                    </td>
                    <td style="width: 25%; text-align: right;">
                        This is software generated invoice
                    </td>
                </tr>
            </table>
        </div>

    </div>
    @endforeach
</body>
</html>
