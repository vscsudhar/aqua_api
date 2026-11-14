@php
    if (!function_exists('formatInvoiceAmount')) {
        function formatInvoiceAmount($val) {
            $num = (float) ($val ?? 0);
            return round($num, 2) == round($num, 0)
                ? number_format($num, 0)
                : number_format($num, 2);
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    @if(!empty($isHtmlView))
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    @endif
    <title>{{ (method_exists($invoice, 'isWithoutGst') ? $invoice->isWithoutGst() : ($invoice->invoice_type ?? '') === 'without_gst') ? 'Invoice' : 'Tax Invoice' }} - {{ $invoice->invoice_no }}</title>
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
        /* ========================================================
           Interactive Screen View Styling (Browser URL /invoices/{id})
           ======================================================== */
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
            margin: 0 auto;
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

        /* Print Layout Rules - Balanced Margins & Zero Clipping */
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
        /* ========================================================
           Strict PDF Print Engine Styling (Dompdf Output)
           ======================================================== */
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

        <!-- TOP HEADER: Logo (Left), Company Name & Address (Center), Copy Marker (Right) -->
        <div style="padding: 12pt 14pt 6pt 14pt;">
            <table style="width: 100%; table-layout: fixed; border-collapse: collapse;">
                <tr>
                    <!-- Left Corner: Logo -->
                    <td style="width: 20%; vertical-align: middle; text-align: left;">
                        @if(!empty($logoBase64))
                            <img src="{{ $logoBase64 }}" style="max-height: 68px; max-width: 130px; object-fit: contain;" alt="Logo" />
                        @endif
                    </td>

                    <!-- Center: Company Name & Address with professional breathing room -->
                    <td style="width: 60%; vertical-align: top; text-align: center; padding: 2pt 8pt 0 8pt;">
                        <div style="font-size: 18pt; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.25; margin-bottom: 4pt;">
                            {{ $settings->company_name }}
                        </div>
                        @if($settings->address)
                            <div style="font-size: 9.8pt; color: #334155; line-height: 1.35; padding: 0 6pt;">
                                {{ $settings->address }}
                            </div>
                        @endif
                    </td>

                    <!-- Right Corner: Copy Marker -->
                    <td style="width: 20%; vertical-align: top; text-align: right; padding-top: 2pt;">
                        <div style="font-size: 8pt; color: #003366; font-weight: bold; border: 0.8pt solid #003366; padding: 3pt 6pt; border-radius: 2.5pt; display: inline-block;">
                            {{ strtoupper($copyType ?? ((method_exists($invoice, 'isWithoutGst') ? $invoice->isWithoutGst() : (($invoice->invoice_type ?? '') === 'without_gst')) ? 'Original for Customer' : 'Original for Recipient')) }}
                        </div>
                    </td>
                </tr>
            </table>

            <!-- COMPANY CONTACT ROW: Left (GSTIN, FSSAI NO) | Right (Phone, Email) -->
            <table style="width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 8pt; font-size: 8.5pt; color: #1e293b; line-height: 1.4;">
                <tr>
                    <!-- Left: GSTIN (bold & font +2) & optional FSSAI NO -->
                    <td style="width: 50%; vertical-align: bottom; text-align: left;">
                        @if(!empty(trim($settings->gstin ?? '')))
                            <div style="font-size: 10.5pt; font-weight: bold; color: #0f172a;"><strong>GSTIN :</strong> {{ $settings->gstin }}</div>
                        @endif
                        @if(!empty(trim($settings->fssai_no ?? '')) && strtolower(trim($settings->fssai_no ?? '')) !== 'null')
                            <div style="margin-top: 2pt; font-size: 8.5pt;"><strong>FSSAI NO :</strong> {{ $settings->fssai_no }}</div>
                        @endif
                    </td>

                    <!-- Right: Phone & Email -->
                    <td style="width: 50%; vertical-align: bottom; text-align: right;">
                        @if(!empty(trim($settings->phone ?? '')))
                            <div><strong>Phone :</strong> {{ $settings->phone }}</div>
                        @endif
                        @if(!empty(trim($settings->email ?? '')))
                            <div style="margin-top: 1.5pt;"><strong>Email :</strong> {{ $settings->email }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <!-- TAX INVOICE / INVOICE STRIP -->
        <div style="text-align: center; font-size: 11pt; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 1.2px; padding: 5pt 0; border-top: 1pt solid #1e293b; border-bottom: 1pt solid #1e293b; background: #ffffff;">
            {{ (method_exists($invoice, 'isWithoutGst') ? $invoice->isWithoutGst() : ($invoice->invoice_type ?? '') === 'without_gst') ? 'INVOICE' : 'TAX INVOICE' }}
        </div>

        <!-- 2-COLUMN BOX: Customer Details (Left) | Invoice Metadata (Right) -->
        <table style="width: 100%; table-layout: fixed; border-collapse: collapse; border-bottom: 1pt solid #1e293b;">
            <tr>
                <!-- Left Box: Customer Details -->
                <td style="width: 54%; vertical-align: top; padding: 8pt 14pt; border-right: 1pt solid #1e293b; font-size: 8.8pt; color: #1e293b; line-height: 1.45;">
                    <div style="font-weight: bold; color: #0f172a; font-size: 10.5pt; margin-bottom: 3pt;">
                        TO : {{ strtoupper(trim(preg_replace('/\s*\((?:COMP|DEALER|CODE)[^\)]*\)/i', '', $invoice->customer_name))) }}
                    </div>
                    @if($invoice->customer_address)
                        <div style="margin-bottom: 2pt; color: #334155; font-size: 8.8pt;">{{ $invoice->customer_address }}</div>
                    @endif
                    @if($invoice->place_of_supply)
                        <div style="margin-bottom: 2pt; font-weight: 500; font-size: 8.8pt;">{{ strtoupper($invoice->place_of_supply) }}</div>
                    @endif
                    @if($invoice->customer_phone)
                        <div><strong>Phone :</strong> {{ $invoice->customer_phone }}</div>
                    @endif
                    @if($invoice->customer_gstin)
                        <div style="margin-top: 2pt; font-size: 9.5pt; font-weight: bold;"><strong>GSTIN :</strong> {{ $invoice->customer_gstin }}</div>
                    @endif
                </td>

                <!-- Right Box: Invoice Metadata -->
                <td style="width: 46%; vertical-align: top; padding: 8pt 14pt; font-size: 8.8pt; line-height: 1.5;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 8.8pt;">
                        <tr>
                            <td style="width: 38%; padding: 2pt 0; font-weight: bold; color: #0f172a;">Invoice No</td>
                            <td style="width: 62%; padding: 2pt 0; font-weight: bold; color: #0f172a;">: {{ $invoice->invoice_no }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 2pt 0; font-weight: bold; color: #0f172a;">Invoice Date</td>
                            <td style="padding: 2pt 0;">: {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td>
                        </tr>
                        @php
                            $paidAmt = (float) ($invoice->paid_amount ?? 0);
                            $grandTot = (float) ($invoice->grand_total ?? 0);
                            $balDue = max(0.0, (float) ($invoice->balance_due ?? ($grandTot - $paidAmt)));
                            if ($paidAmt <= 0) {
                                $payStatusText = 'UNPAID';
                                $payStatusColor = '#dc2626';
                                $payStatusBg = '#fef2f2';
                                $payStatusBorder = '#fecaca';
                            } elseif ($paidAmt < $grandTot) {
                                $payStatusText = 'PARTIALLY PAID';
                                $payStatusColor = '#d97706';
                                $payStatusBg = '#fffbeb';
                                $payStatusBorder = '#fde68a';
                            } else {
                                $payStatusText = 'FULLY PAID';
                                $payStatusColor = '#16a34a';
                                $payStatusBg = '#f0fdf4';
                                $payStatusBorder = '#bbf7d0';
                            }
                        @endphp
                        <tr>
                            <td style="padding: 2pt 0; font-weight: bold; color: #0f172a;">Payment Status</td>
                            <td style="padding: 2pt 0; font-weight: bold; color: {{ $payStatusColor }};">: {{ $payStatusText }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 2pt 0; font-weight: bold; color: #0f172a;">Payment Mode</td>
                            <td style="padding: 2pt 0; text-transform: uppercase;">: {{ $invoice->payment_status === 'paid' ? 'CASH' : ($invoice->payment_mode ?? 'CASH') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- LINE ITEMS TABLE -->
        @if(!empty($isHtmlView))
        <div class="table-responsive-wrapper">
        @endif
        @php
            $isWithoutGst = method_exists($invoice, 'isWithoutGst') ? $invoice->isWithoutGst() : (($invoice->invoice_type ?? '') === 'without_gst');
        @endphp
        <table style="width: 100%; table-layout: fixed; border-collapse: collapse; border-bottom: 1pt solid #1e293b; margin-top: {{ $isWithoutGst ? '10pt' : '0' }}; @if($isWithoutGst) border-top: 1pt solid #1e293b; @endif">
            <thead>
                <tr style="border-bottom: 1pt solid #1e293b; background: #ffffff;">
                    <th style="width: 5%; padding: 5pt 3pt; font-size: 7.2pt; font-weight: bold; text-align: center; border-right: 1pt solid #1e293b;">S.No</th>
                    <th style="width: {{ $isWithoutGst ? '38%' : '31%' }}; padding: 5pt 4pt; font-size: 7.2pt; font-weight: bold; text-align: left; border-right: 1pt solid #1e293b;">Prod Name</th>
                    <th style="width: 10%; padding: 5pt 3pt; font-size: 7.2pt; font-weight: bold; text-align: center; border-right: 1pt solid #1e293b;">HSN</th>
                    <th style="width: 7%; padding: 5pt 3pt; font-size: 7.2pt; font-weight: bold; text-align: center; border-right: 1pt solid #1e293b;">Qty</th>
                    <th style="width: 7%; padding: 5pt 3pt; font-size: 7.2pt; font-weight: bold; text-align: center; border-right: 1pt solid #1e293b;">UOM</th>
                    <th style="width: {{ $isWithoutGst ? '12%' : '9%' }}; padding: 5pt 3pt; font-size: 7.2pt; font-weight: bold; text-align: right; border-right: 1pt solid #1e293b;">Rate</th>
                    <th style="width: {{ $isWithoutGst ? '11%' : '11%' }}; padding: 5pt 3pt; font-size: 7.2pt; font-weight: bold; text-align: right; border-right: 1pt solid #1e293b;">Amount</th>
                    @if(!$isWithoutGst)
                    <th style="width: 9%; padding: 5pt 3pt; font-size: 7.2pt; font-weight: bold; text-align: right; border-right: 1pt solid #1e293b;">Tax Amount</th>
                    @endif
                    <th style="width: {{ $isWithoutGst ? '10%' : '11%' }}; padding: 5pt 3pt; font-size: 7.2pt; font-weight: bold; text-align: right;">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $idx => $item)
                @php
                    $itemTax = (float)($item->cgst_amount + $item->sgst_amount + $item->igst_amount);
                @endphp
                <tr style="border-bottom: 0.5pt solid #cbd5e1; font-size: 7.2pt;">
                    <td style="padding: 4.5pt 3pt; text-align: center; border-right: 1pt solid #1e293b;">{{ $idx + 1 }}</td>
                    <td style="padding: 4.5pt 4pt; text-align: left; border-right: 1pt solid #1e293b;">
                        <strong>{{ $item->item_description }}</strong>
                        @if($item->notes)
                            <div style="font-size: 6.8pt; color: #64748b;">{{ $item->notes }}</div>
                        @endif
                    </td>
                    <td style="padding: 4.5pt 3pt; text-align: center; border-right: 1pt solid #1e293b;">{{ $item->hsn_sac }}</td>
                    <td style="padding: 4.5pt 3pt; text-align: center; border-right: 1pt solid #1e293b;">{{ number_format($item->quantity, 0) }}</td>
                    <td style="padding: 4.5pt 3pt; text-align: center; border-right: 1pt solid #1e293b;">{{ $item->unit ?? 'Pcs' }}</td>
                    <td style="padding: 4.5pt 3pt; text-align: right; border-right: 1pt solid #1e293b;">{{ formatInvoiceAmount($item->unit_price) }}</td>
                    <td style="padding: 4.5pt 3pt; text-align: right; border-right: 1pt solid #1e293b;">{{ formatInvoiceAmount($item->taxable_amount) }}</td>
                    @if(!$isWithoutGst)
                    <td style="padding: 4.5pt 3pt; text-align: right; border-right: 1pt solid #1e293b;">{{ formatInvoiceAmount($itemTax) }}</td>
                    @endif
                    <td style="padding: 4.5pt 3pt; text-align: right; font-weight: bold;">{{ formatInvoiceAmount($item->line_total) }}</td>
                </tr>
                @endforeach

                <!-- Empty Filler Rows to maintain consistent visual invoice height -->
                @php
                    $targetRows = $isWithoutGst ? 14 : 12;
                    $emptyRows = max(0, $targetRows - count($invoice->items));
                @endphp
                @for($i = 0; $i < $emptyRows; $i++)
                <tr style="height: 19pt;">
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    @if(!$isWithoutGst)
                    <td style="border-right: 1pt solid #1e293b;">&nbsp;</td>
                    @endif
                    <td>&nbsp;</td>
                </tr>
                @endfor

                <!-- Summary Row inside Items Table -->
                @php
                    $totalQty = $invoice->items->sum('quantity');
                    $totalTax = (float)($invoice->tax_total ?? ($invoice->cgst_amount + $invoice->sgst_amount + $invoice->igst_amount));
                    $totalLineSum = $invoice->items->sum('line_total');
                @endphp
                <tr style="border-top: 1pt solid #1e293b; font-size: 7.2pt; font-weight: bold;">
                    <td style="padding: 4.5pt 3pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4.5pt 4pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4.5pt 3pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4.5pt 3pt; text-align: center; border-right: 1pt solid #1e293b;">{{ number_format($totalQty, 0) }}</td>
                    <td style="padding: 4.5pt 3pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4.5pt 3pt; border-right: 1pt solid #1e293b;">&nbsp;</td>
                    <td style="padding: 4.5pt 3pt; text-align: right; border-right: 1pt solid #1e293b;">{{ formatInvoiceAmount($invoice->taxable_amount) }}</td>
                    @if(!$isWithoutGst)
                    <td style="padding: 4.5pt 3pt; text-align: right; border-right: 1pt solid #1e293b;">{{ formatInvoiceAmount($totalTax) }}</td>
                    @endif
                    <td style="padding: 4.5pt 3pt; text-align: right;">{{ formatInvoiceAmount($totalLineSum) }}</td>
                </tr>
            </tbody>
        </table>
        @if(!empty($isHtmlView))
        </div>
        @endif

        <!-- BOTTOM 2-COLUMN SECTION: Bank Details (Left) | Net Amount & Words (Right) -->
        <table style="width: 100%; table-layout: fixed; border-collapse: collapse; border-bottom: 1pt solid #1e293b;">
            <tr>
                <!-- Left: Bank Details -->
                <td style="width: 54%; vertical-align: top; padding: 10pt 14pt; border-right: 1pt solid #1e293b; font-size: 8.5pt; line-height: 1.4;">
                    <div style="font-weight: bold; font-size: 9.5pt; color: #0f172a; margin-bottom: 4pt;">Bank Details</div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 8.5pt;">
                        <tr>
                            <td style="width: 25%; padding: 2pt 0; color: #334155;">Name</td>
                            <td style="width: 75%; padding: 2pt 0; font-weight: 600; color: #0f172a;">: {{ $settings->company_name }}</td>
                        </tr>
                        @if($settings->account_number)
                        <tr>
                            <td style="padding: 2pt 0; color: #334155;">A/C No</td>
                            <td style="padding: 2pt 0; font-weight: bold; color: #0f172a;">: {{ $settings->account_number }}</td>
                        </tr>
                        @endif
                        @if($settings->bank_name)
                        <tr>
                            <td style="padding: 2pt 0; color: #334155;">Bank</td>
                            <td style="padding: 2pt 0;">: {{ $settings->bank_name }}</td>
                        </tr>
                        @endif
                        @if($settings->ifsc_code)
                        <tr>
                            <td style="padding: 2pt 0; color: #334155;">IFSC Code</td>
                            <td style="padding: 2pt 0; font-weight: bold; color: #0f172a;">: {{ $settings->ifsc_code }}</td>
                        </tr>
                        @endif
                        @if($settings->bank_branch)
                        <tr>
                            <td style="padding: 2pt 0; color: #334155;">Branch</td>
                            <td style="padding: 2pt 0;">: {{ $settings->bank_branch }}</td>
                        </tr>
                        @endif
                    </table>

                    @if(!empty(trim($invoice->notes ?? '')))
                    <div style="margin-top: 5pt; font-size: 8pt; color: #475569;">
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
                    <div style="margin-top: 5pt; padding: 4pt 6pt; background-color: #f8fafc; border: 0.5pt solid #cbd5e1; border-radius: 3pt;">
                        <div style="font-size: 8pt; font-weight: bold; color: #0284c7; text-transform: uppercase; margin-bottom: 2pt;">Declaration / Disclaimer:</div>
                        <div style="font-size: 7.8pt; color: #334155; line-height: 1.35; white-space: pre-line;">{!! nl2br(e($disclaimerText)) !!}</div>
                    </div>
                    @endif
                </td>

                <!-- Right: Net Amount & Words -->
                <td style="width: 46%; vertical-align: top; padding: 10pt 14pt;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 8.8pt;">
                        <tr>
                            <td style="color: #334155; padding: 2pt 0;">Subtotal:</td>
                            <td style="text-align: right; font-weight: 500; padding: 2pt 0;">₹{{ formatInvoiceAmount($invoice->subtotal) }}</td>
                        </tr>
                        @if($invoice->discount_total > 0)
                        <tr>
                            <td style="color: #16a34a; padding: 2pt 0;">Discount:</td>
                            <td style="text-align: right; color: #16a34a; padding: 2pt 0;">- ₹{{ formatInvoiceAmount($invoice->discount_total) }}</td>
                        </tr>
                        @endif
                        @if(!$isWithoutGst)
                        <tr>
                            <td style="color: #334155; padding: 2pt 0; font-weight: 600;">Taxable Amount:</td>
                            <td style="text-align: right; font-weight: 600; padding: 2pt 0;">₹{{ formatInvoiceAmount($invoice->taxable_amount) }}</td>
                        </tr>
                        @if($invoice->is_interstate)
                        <tr>
                            <td style="color: #334155; padding: 2pt 0;">IGST:</td>
                            <td style="text-align: right; padding: 2pt 0;">₹{{ formatInvoiceAmount($invoice->igst_amount) }}</td>
                        </tr>
                        @else
                        <tr>
                            <td style="color: #334155; padding: 2pt 0;">CGST:</td>
                            <td style="text-align: right; padding: 2pt 0;">₹{{ formatInvoiceAmount($invoice->cgst_amount) }}</td>
                        </tr>
                        <tr>
                            <td style="color: #334155; padding: 2pt 0;">SGST:</td>
                            <td style="text-align: right; padding: 2pt 0;">₹{{ formatInvoiceAmount($invoice->sgst_amount) }}</td>
                        </tr>
                        @endif
                        @endif
                        @if(abs($invoice->round_off) > 0.001)
                        <tr>
                            <td style="color: #0284c7; padding: 2pt 0;">Round Off:</td>
                            <td style="text-align: right; color: #0284c7; padding: 2pt 0;">{{ $invoice->round_off > 0 ? '+ ' : '- ' }}₹{{ formatInvoiceAmount(abs($invoice->round_off)) }}</td>
                        </tr>
                        @endif
                        <tr style="border-top: 0.5pt solid #cbd5e1;">
                            <td style="font-size: 10.5pt; font-weight: bold; color: #0284c7; padding: 3pt 0;">Net Amount</td>
                            <td style="font-size: 13.5pt; font-weight: bold; text-align: right; color: #0284c7; padding: 3pt 0;">₹{{ formatInvoiceAmount($invoice->grand_total) }}</td>
                        </tr>
                    </table>

                    <div style="font-size: 8.5pt; color: #334155; margin-top: 5pt; line-height: 1.35;">
                        <strong>In Words :</strong> {{ $amountInWords }}
                    </div>

                    <!-- PAYMENT SUMMARY CARD (Clear Status, Amount Paid, Balance Due) -->
                    <div style="margin-top: 7pt; border: 0.5pt solid {{ $payStatusBorder }}; background-color: {{ $payStatusBg }}; border-radius: 4pt; padding: 5pt 8pt;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 8.5pt;">
                            <tr>
                                <td style="padding: 2pt 0; color: #475569; font-weight: 600;">Payment Status:</td>
                                <td style="text-align: right; padding: 2pt 0;">
                                    <span style="font-weight: bold; font-size: 8.8pt; color: {{ $payStatusColor }}; letter-spacing: 0.5px;">
                                        {{ $payStatusText }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2pt 0; color: #475569;">Amount Paid:</td>
                                <td style="text-align: right; font-weight: bold; color: #16a34a; padding: 2pt 0;">
                                    ₹{{ formatInvoiceAmount($paidAmt) }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2pt 0; color: #475569;">Balance Due:</td>
                                <td style="text-align: right; font-weight: bold; color: {{ $balDue > 0 ? '#dc2626' : '#16a34a' }}; padding: 2pt 0;">
                                    ₹{{ formatInvoiceAmount($balDue) }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <!-- SIGNATURES & BOTTOM BAR -->
        <div style="padding: 10pt 14pt 8pt 14pt;">
            <!-- Company Sign-off Line -->
            <div style="text-align: right; font-size: 9pt; font-weight: bold; color: #0f172a; margin-bottom: 26pt;">
                for {{ $settings->company_name }}
            </div>

            <!-- Signatures Row -->
            <table style="width: 100%; table-layout: fixed; font-size: 8.5pt; color: #0f172a; margin-bottom: 8pt;">
                <tr>
                    <td style="width: 33%; text-align: left; font-weight: 600;">Receiver Signature</td>
                    <td style="width: 34%; text-align: center; font-weight: 600;">Checked by</td>
                    <td style="width: 33%; text-align: right; font-weight: 600;">Authorized Signature</td>
                </tr>
            </table>

            <!-- Bottom Disclaimer Bar -->
            <table style="width: 100%; table-layout: fixed; border-top: 0.5pt solid #cbd5e1; padding-top: 5pt; font-size: 7.8pt; color: #475569;">
                <tr>
                    <td style="width: 30%; text-align: left;">&nbsp;</td>
                    <td style="width: 40%; text-align: center; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">THANK YOU! VISIT AGAIN</td>
                    <td style="width: 30%; text-align: right;">This is software generated invoice</td>
                </tr>
            </table>
        </div>

    </div>
</body>
</html>
