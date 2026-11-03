<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bottle Ledger - {{ $meta['month_name'] }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 6mm 8mm 6mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 7pt;
            color: #1a202c;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .header {
            text-align: center;
            margin-bottom: 6px;
            border-bottom: 2px solid #0f3460;
            padding-bottom: 4px;
            position: relative;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }
        .company-title {
            font-size: 15pt;
            font-weight: bold;
            color: #0f3460;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin: 0;
        }
        .report-subtitle {
            font-size: 10.5pt;
            font-weight: bold;
            color: #16213e;
            margin: 2px 0;
        }
        .meta-line {
            font-size: 8pt;
            color: #475569;
            font-weight: 500;
        }
        
        .tables-wrapper {
            width: 100%;
            margin-top: 4px;
        }
        .dual-container {
            width: 100%;
            border-collapse: collapse;
        }
        .dual-container > tbody > tr > td {
            vertical-align: top;
            padding: 0;
            border: none;
        }
        
        .table-panel {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.5pt;
        }
        .table-panel th, .table-panel td {
            border: 0.5pt solid #94a3b8;
            padding: 2px 1.5px;
            text-align: right;
            white-space: nowrap;
        }
        .table-panel th.col-date, .table-panel td.col-date {
            text-align: center;
            width: 22px;
            font-weight: 600;
        }
        .table-panel th.col-total, .table-panel td.col-total {
            font-weight: bold;
            background-color: #f1f5f9;
        }
        .th-title {
            background-color: #0f3460;
            color: #ffffff;
            font-size: 7.5pt;
            font-weight: bold;
            text-align: center !important;
            padding: 3px 2px;
            text-transform: uppercase;
        }
        .th-title-supplier {
            background-color: #005f73;
            color: #ffffff;
            font-size: 7.5pt;
            font-weight: bold;
            text-align: center !important;
            padding: 3px 2px;
            text-transform: uppercase;
        }
        .th-col {
            background-color: #e2e8f0;
            color: #0f172a;
            font-weight: bold;
            font-size: 6.5pt;
            text-align: center !important;
        }
        .row-opening {
            background-color: #fef3c7;
            color: #92400e;
            font-weight: bold;
        }
        .row-even {
            background-color: #f8fafc;
        }
        .row-total {
            background-color: #e2e8f0;
            font-weight: bold;
            border-top: 1.5pt solid #0f172a;
            border-bottom: 2pt double #0f172a;
        }
        .row-total td {
            font-size: 6.8pt;
            font-weight: bold;
        }

        /* Stock Register Summary styling */
        .stock-section {
            margin-top: 8px;
            page-break-inside: avoid;
        }
        .stock-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        .stock-table th, .stock-table td {
            border: 0.5pt solid #64748b;
            padding: 3px 4px;
            text-align: center;
        }
        .stock-table th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7pt;
        }
        .stock-table td.num {
            text-align: right;
            font-weight: 600;
        }
        .stock-table .stock-title {
            background-color: #334155;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding-left: 6px;
        }

        .footer-table {
            width: 100%;
            margin-top: 12px;
            border-collapse: collapse;
            font-size: 7pt;
            color: #64748b;
        }
        .footer-table td {
            border: none;
            padding: 0;
            vertical-align: bottom;
        }
        .sign-line {
            border-top: 1px dashed #94a3b8;
            width: 120px;
            display: inline-block;
            margin-bottom: 3px;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 15%; text-align: left;">
                    @if(!empty($meta['company_logo']))
                        <img src="{{ $meta['company_logo'] }}" style="max-height: 38px; max-width: 120px;" alt="Logo" />
                    @endif
                </td>
                <td style="width: 70%; text-align: center;">
                    <div class="company-title">{{ $meta['company_name'] }}</div>
                    <div class="report-subtitle">BOTTLE LEDGER – MONTHLY PURCHASE &amp; STOCK REGISTER</div>
                    <div class="meta-line">MONTH: <strong>{{ strtoupper($meta['month_name']) }}</strong> | PRINTED: {{ date('d-m-Y H:i') }}</div>
                </td>
                <td style="width: 15%; text-align: right; font-size: 7pt; color: #64748b;">
                    <div>TRADITIONAL LEDGER</div>
                    <div>CURRENCY: INR (&#8377;)</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Dual Table Side-by-Side -->
    <div class="tables-wrapper">
        <table class="dual-container">
            <tr>
                <!-- LEFT TABLE: Dealer Bottle Purchase (Without Refill) -->
                <td style="width: 49.5%;">
                    <table class="table-panel">
                        <thead>
                            <tr>
                                <th colspan="19" class="th-title">
                                    LEFT SIDE &ndash; DEALER BOTTLE PURCHASE (WITHOUT REFILL)
                                </th>
                            </tr>
                            <tr>
                                <th rowspan="2" class="th-col col-date" style="vertical-align: middle;">Date</th>
                                @foreach($meta['product_keys'] as $pk)
                                    <th colspan="2" class="th-col">{{ $pk }}</th>
                                @endforeach
                                <th colspan="2" class="th-col col-total">Total</th>
                            </tr>
                            <tr>
                                @foreach($meta['product_keys'] as $pk)
                                    <th class="th-col" style="font-size: 5pt; padding: 1px;">Qty</th>
                                    <th class="th-col" style="font-size: 5pt; padding: 1px;">Amt</th>
                                @endforeach
                                <th class="th-col col-total" style="font-size: 5pt; padding: 1px;">Qty</th>
                                <th class="th-col col-total" style="font-size: 5pt; padding: 1px;">Amt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Opening Stock Row -->
                            <tr class="row-opening">
                                <td class="col-date">Op</td>
                                @foreach($meta['product_keys'] as $pk)
                                    <td>{{ number_format($opening_stock[$pk] ?? 0, 0) }}</td>
                                    <td>-</td>
                                @endforeach
                                <td class="col-total">-</td>
                                <td class="col-total">-</td>
                            </tr>
                            <!-- Daily Rows -->
                            @foreach($dealer_table['rows'] as $r)
                                <tr class="{{ $loop->even ? 'row-even' : '' }}">
                                    <td class="col-date">{{ $r['day'] }}</td>
                                    @foreach($meta['product_keys'] as $pk)
                                        <td>{{ ($r['quantities'][$pk] ?? 0) > 0 ? number_format($r['quantities'][$pk], 0) : '-' }}</td>
                                        <td>{{ ($r['products'][$pk] ?? 0) > 0 ? number_format($r['products'][$pk], 0) : '-' }}</td>
                                    @endforeach
                                    <td class="col-total">{{ ($r['total_quantity'] ?? 0) > 0 ? number_format($r['total_quantity'], 0) : '-' }}</td>
                                    <td class="col-total">{{ ($r['total_amount'] ?? 0) > 0 ? number_format($r['total_amount'], 0) : '-' }}</td>
                                </tr>
                            @endforeach
                            <!-- Monthly Total Row -->
                            <tr class="row-total">
                                <td class="col-date">Total</td>
                                @foreach($meta['product_keys'] as $pk)
                                    <td>{{ number_format($dealer_table['monthly_qty_totals'][$pk] ?? 0, 0) }}</td>
                                    <td>{{ number_format($dealer_table['monthly_totals'][$pk] ?? 0, 0) }}</td>
                                @endforeach
                                <td class="col-total">{{ number_format($dealer_table['grand_qty_total'] ?? 0, 0) }}</td>
                                <td class="col-total">&#8377; {{ number_format($dealer_table['grand_total'], 0) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </td>

                <td style="width: 1%;"></td><!-- Divider Spacer -->

                <!-- RIGHT TABLE: Supplier Bottle Purchase -->
                <td style="width: 49.5%;">
                    <table class="table-panel">
                        <thead>
                            <tr>
                                <th colspan="10" class="th-title-supplier">
                                    RIGHT SIDE &ndash; SUPPLIER BOTTLE PURCHASE
                                </th>
                            </tr>
                            <tr>
                                <th class="th-col col-date">Date</th>
                                @foreach($meta['product_keys'] as $pk)
                                    <th class="th-col">{{ $pk }}</th>
                                @endforeach
                                <th class="th-col col-total">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Opening Stock Row -->
                            <tr class="row-opening">
                                <td class="col-date">Op</td>
                                @foreach($meta['product_keys'] as $pk)
                                    <td>{{ number_format($opening_stock[$pk] ?? 0, 0) }} Qty</td>
                                @endforeach
                                <td class="col-total">-</td>
                            </tr>
                            <!-- Daily Rows -->
                            @foreach($supplier_table['rows'] as $r)
                                <tr class="{{ $loop->even ? 'row-even' : '' }}">
                                    <td class="col-date">{{ $r['day'] }}</td>
                                    @foreach($meta['product_keys'] as $pk)
                                        <td>{{ ($r['products'][$pk] ?? 0) > 0 ? number_format($r['products'][$pk], 2) : '-' }}</td>
                                    @endforeach
                                    <td class="col-total">{{ ($r['total_amount'] ?? 0) > 0 ? number_format($r['total_amount'], 2) : '-' }}</td>
                                </tr>
                            @endforeach
                            <!-- Monthly Total Row -->
                            <tr class="row-total">
                                <td class="col-date">Total</td>
                                @foreach($meta['product_keys'] as $pk)
                                    <td>{{ number_format($supplier_table['monthly_totals'][$pk] ?? 0, 2) }}</td>
                                @endforeach
                                <td class="col-total">&#8377; {{ number_format($supplier_table['grand_total'], 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <!-- Stock Register Summary (Quantities) -->
    <div class="stock-section">
        <table class="stock-table">
            <thead>
                <tr>
                    <th colspan="10" class="stock-title">
                        MONTHLY BOTTLE STOCK REGISTER &ndash; QUANTITY AUDIT SUMMARY
                    </th>
                </tr>
                <tr>
                    <th style="width: 19%; text-align: left; padding-left: 6px;">Particulars</th>
                    @foreach($meta['product_keys'] as $pk)
                        <th style="width: 9%;">{{ $pk }}</th>
                    @endforeach
                    <th style="width: 9%;">Grand Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: left; font-weight: bold; background-color: #fef3c7;">Opening Stock (Qty)</td>
                    @php $sumOp = 0; @endphp
                    @foreach($stock_register as $sr)
                        @php $sumOp += $sr['opening_qty']; @endphp
                        <td class="num">{{ number_format($sr['opening_qty'], 0) }}</td>
                    @endforeach
                    <td class="num" style="font-weight: bold;">{{ number_format($sumOp, 0) }}</td>
                </tr>
                <tr>
                    <td style="text-align: left; font-weight: bold; background-color: #ecfdf5;">(+) Supplier Purchases Inward (Qty)</td>
                    @php $sumIn = 0; @endphp
                    @foreach($stock_register as $sr)
                        @php $sumIn += $sr['supplier_inward_qty']; @endphp
                        <td class="num">{{ number_format($sr['supplier_inward_qty'], 0) }}</td>
                    @endforeach
                    <td class="num" style="font-weight: bold;">{{ number_format($sumIn, 0) }}</td>
                </tr>
                <tr>
                    <td style="text-align: left; font-weight: bold; background-color: #fef2f2;">(&minus;) Dealer Sales Outward [Without Refill] (Qty)</td>
                    @php $sumOut = 0; @endphp
                    @foreach($stock_register as $sr)
                        @php $sumOut += $sr['dealer_outward_qty']; @endphp
                        <td class="num">{{ number_format($sr['dealer_outward_qty'], 0) }}</td>
                    @endforeach
                    <td class="num" style="font-weight: bold;">{{ number_format($sumOut, 0) }}</td>
                </tr>
                <tr style="background-color: #e2e8f0; font-weight: bold; border-top: 1.5pt solid #0f172a;">
                    <td style="text-align: left; font-weight: bold;">(=) Closing Stock (Qty)</td>
                    @php $sumCl = 0; @endphp
                    @foreach($stock_register as $sr)
                        @php $sumCl += $sr['closing_qty']; @endphp
                        <td class="num" style="font-weight: bold;">{{ number_format($sr['closing_qty'], 0) }}</td>
                    @endforeach
                    <td class="num" style="font-weight: bold;">{{ number_format($sumCl, 0) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Signatures & Verification -->
    <table class="footer-table">
        <tr>
            <td style="width: 33%; text-align: left;">
                <span class="sign-line"></span><br>
                Prepared By (Store Keeper)
            </td>
            <td style="width: 34%; text-align: center;">
                <span class="sign-line"></span><br>
                Verified By (Accountant)
            </td>
            <td style="width: 33%; text-align: right;">
                <span class="sign-line"></span><br>
                Authorized Signatory
            </td>
        </tr>
    </table>

</body>
</html>

