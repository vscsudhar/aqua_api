<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report_meta['title'] }} - {{ $report_meta['month_name'] }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 6mm 8mm 6mm;
        }
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
        body {
            font-family: 'Noto Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 7.5pt;
            color: #1a202c;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .header {
            text-align: center;
            margin-bottom: 6px;
            border-bottom: 2px solid #003366;
            padding-bottom: 4px;
        }
        .company-title {
            font-size: 14pt;
            font-weight: bold;
            color: #003366;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .report-subtitle {
            font-size: 9.5pt;
            font-weight: bold;
            color: #007791;
            margin: 2px 0;
        }
        .meta-line {
            font-size: 7pt;
            color: #64748b;
        }
        table.matrix {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            table-layout: fixed;
        }
        table.matrix th, table.matrix td {
            border: 0.5pt solid #cbd5e1;
            padding: 2.5px 2px;
            text-align: center;
            font-size: 6.8pt;
            overflow: hidden;
            word-wrap: break-word;
        }
        thead {
            display: table-header-group;
        }
        tr {
            page-break-inside: avoid;
        }
        .th-main {
            background-color: #003366;
            color: #ffffff;
            font-weight: bold;
            font-size: 7pt;
        }
        .th-date {
            width: 38px;
        }
        .th-day {
            width: 22px;
        }
        .th-total {
            width: 48px;
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
        }
        .period-banner {
            background-color: #e2e8f0;
            font-weight: bold;
            font-size: 7.5pt;
            color: #0f172a;
            text-align: left;
            padding-left: 6px;
        }
        .row-opening {
            background-color: #fef3c7;
            color: #92400e;
            font-weight: bold;
        }
        .row-even {
            background-color: #f8fafc;
        }
        .row-canes {
            background-color: #e0f2fe;
            color: #0369a1;
            font-weight: bold;
        }
        .row-filling {
            background-color: #e0e7ff;
            color: #3730a3;
            font-weight: bold;
        }
        .row-due {
            background-color: #ffedd5;
            color: #c2410c;
            font-weight: bold;
        }
        .row-payments {
            background-color: #dcfce7;
            color: #15803d;
            font-weight: bold;
        }
        .row-closing {
            background-color: #ffe4e6;
            color: #be123c;
            font-weight: bold;
        }
        .legend {
            margin-top: 8px;
            font-size: 6.5pt;
            color: #475569;
        }
        .legend-box {
            display: inline-block;
            width: 9px;
            height: 9px;
            margin-right: 2px;
            vertical-align: middle;
            border: 0.5pt solid #94a3b8;
        }
        .product-summary-table {
            width: 60%;
            margin-top: 8px;
            border-collapse: collapse;
            font-size: 6.8pt;
        }
        .product-summary-table th, .product-summary-table td {
            border: 0.5pt solid #cbd5e1;
            padding: 2.5px 5px;
        }
        .product-summary-table th {
            background-color: #003366;
            color: #ffffff;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="company-title">{{ $company['name'] }}</h1>
        <div class="report-subtitle">{{ $report_meta['title'] }} — {{ strtoupper($report_meta['month_name']) }}</div>
        <div class="meta-line">
            {{ $company['address'] }} | GSTIN: {{ $company['gstin'] }} | Primary Cane Metric: {{ $report_meta['cane_product']['name'] }} ({{ $report_meta['cane_product']['unit'] }})
        </div>
    </div>

    @php
        $dealerCount = count($dealers);
    @endphp

    <table class="matrix">
        <thead>
            <tr>
                <th class="th-main th-date">Date</th>
                <th class="th-main th-day">Day</th>
                @foreach($dealers as $d)
                    <th class="th-main">{{ $d['name'] }}<br><span style="font-size:5.5pt;font-weight:normal;">({{ $d['code'] }})</span></th>
                @endforeach
                <th class="th-main" style="background-color:#1e293b;">Others<br><span style="font-size:5.5pt;font-weight:normal;">(Retail)</span></th>
                <th class="th-main th-total">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            {{-- PERIOD 1 (Days 1 - 15) --}}
            <tr>
                <td colspan="{{ 3 + $dealerCount + 1 }}" class="period-banner">
                    ▶ PERIOD 1 : DAYS 01 - 15 ({{ strtoupper($report_meta['month_name']) }})
                </td>
            </tr>

            {{-- Period 1 Opening Balance --}}
            <tr class="row-opening">
                <td colspan="2" style="text-align:right;padding-right:4px;">OPENING BAL</td>
                @foreach($dealers as $d)
                    <td>{{ number_format($period1_summary['dealers'][$d['id']]['opening_balance'], 2) }}</td>
                @endforeach
                <td>{{ number_format($period1_summary['others']['opening_balance'], 2) }}</td>
                <td style="font-weight:bold;">{{ number_format($period1_summary['totals']['opening_balance'], 2) }}</td>
            </tr>

            {{-- Period 1 Daily Rows --}}
            @for($day = 1; $day <= 15; $day++)
                @php $dData = $dates[$day - 1] ?? null; @endphp
                @if($dData)
                    <tr class="{{ $day % 2 == 0 ? 'row-even' : '' }}">
                        <td>{{ $dData['formatted'] }}</td>
                        <td>{{ $dData['day_name'] }}</td>
                        @foreach($dealers as $d)
                            @php $ent = $dData['dealers'][$d['id']]; @endphp
                            <td>
                                @if($ent['cane_qty'] > 0 || $ent['filling_amount'] > 0 || $ent['payments_received'] > 0)
                                    <b>{{ $ent['cane_qty'] }}</b>
                                    @if($ent['payments_received'] > 0)
                                        <br><span style="color:#15803d;font-size:5.8pt;">₹{{ round($ent['payments_received']) }}</span>
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                        <td>
                            @if($dData['others']['cane_qty'] > 0 || $dData['others']['payments_received'] > 0)
                                <b>{{ $dData['others']['cane_qty'] }}</b>
                                @if($dData['others']['payments_received'] > 0)
                                    <br><span style="color:#15803d;font-size:5.8pt;">₹{{ round($dData['others']['payments_received']) }}</span>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td style="font-weight:bold;background-color:#f1f5f9;">
                            <b>{{ $dData['totals']['cane_qty'] }}</b>
                            @if($dData['totals']['filling_amount'] > 0)
                                <br><span style="font-size:5.8pt;color:#003366;">₹{{ number_format($dData['totals']['filling_amount'], 0) }}</span>
                            @endif
                        </td>
                    </tr>
                @endif
            @endfor

            {{-- Period 1 Summary Rows --}}
            <tr class="row-canes">
                <td colspan="2" style="text-align:right;">P1 TOTAL CANES</td>
                @foreach($dealers as $d)
                    <td>{{ $period1_summary['dealers'][$d['id']]['total_canes'] }}</td>
                @endforeach
                <td>{{ $period1_summary['others']['total_canes'] }}</td>
                <td>{{ $period1_summary['totals']['total_canes'] }}</td>
            </tr>
            <tr class="row-filling">
                <td colspan="2" style="text-align:right;">P1 FILLING (₹)</td>
                @foreach($dealers as $d)
                    <td>{{ number_format($period1_summary['dealers'][$d['id']]['total_filling'], 2) }}</td>
                @endforeach
                <td>{{ number_format($period1_summary['others']['total_filling'], 2) }}</td>
                <td>{{ number_format($period1_summary['totals']['total_filling'], 2) }}</td>
            </tr>
            <tr class="row-due">
                <td colspan="2" style="text-align:right;">P1 TOTAL DUE (₹)</td>
                @foreach($dealers as $d)
                    <td>{{ number_format($period1_summary['dealers'][$d['id']]['total_due'], 2) }}</td>
                @endforeach
                <td>{{ number_format($period1_summary['others']['total_due'], 2) }}</td>
                <td>{{ number_format($period1_summary['totals']['total_due'], 2) }}</td>
            </tr>
            <tr class="row-payments">
                <td colspan="2" style="text-align:right;">P1 PAYMENTS (₹)</td>
                @foreach($dealers as $d)
                    <td>{{ number_format($period1_summary['dealers'][$d['id']]['total_payments'], 2) }}</td>
                @endforeach
                <td>{{ number_format($period1_summary['others']['total_payments'], 2) }}</td>
                <td>{{ number_format($period1_summary['totals']['total_payments'], 2) }}</td>
            </tr>
            <tr class="row-closing">
                <td colspan="2" style="text-align:right;">P1 CLOSING BAL</td>
                @foreach($dealers as $d)
                    <td>{{ number_format($period1_summary['dealers'][$d['id']]['closing_balance'], 2) }}</td>
                @endforeach
                <td>{{ number_format($period1_summary['others']['closing_balance'], 2) }}</td>
                <td>{{ number_format($period1_summary['totals']['closing_balance'], 2) }}</td>
            </tr>

            {{-- PERIOD 2 (Days 16 - End) --}}
            <tr>
                <td colspan="{{ 3 + $dealerCount + 1 }}" class="period-banner" style="background-color:#cbd5e1;margin-top:6px;">
                    ▶ PERIOD 2 : DAYS 16 - {{ $report_meta['days_in_month'] }} ({{ strtoupper($report_meta['month_name']) }})
                </td>
            </tr>

            {{-- Period 2 Opening Balance (Equals Period 1 Closing Balance) --}}
            <tr class="row-opening">
                <td colspan="2" style="text-align:right;padding-right:4px;">P2 OPENING BAL</td>
                @foreach($dealers as $d)
                    <td>{{ number_format($period2_summary['dealers'][$d['id']]['opening_balance'], 2) }}</td>
                @endforeach
                <td>{{ number_format($period2_summary['others']['opening_balance'], 2) }}</td>
                <td style="font-weight:bold;">{{ number_format($period2_summary['totals']['opening_balance'], 2) }}</td>
            </tr>

            {{-- Period 2 Daily Rows --}}
            @for($day = 16; $day <= $report_meta['days_in_month']; $day++)
                @php $dData = $dates[$day - 1] ?? null; @endphp
                @if($dData)
                    <tr class="{{ $day % 2 == 0 ? 'row-even' : '' }}">
                        <td>{{ $dData['formatted'] }}</td>
                        <td>{{ $dData['day_name'] }}</td>
                        @foreach($dealers as $d)
                            @php $ent = $dData['dealers'][$d['id']]; @endphp
                            <td>
                                @if($ent['cane_qty'] > 0 || $ent['filling_amount'] > 0 || $ent['payments_received'] > 0)
                                    <b>{{ $ent['cane_qty'] }}</b>
                                    @if($ent['payments_received'] > 0)
                                        <br><span style="color:#15803d;font-size:5.8pt;">₹{{ round($ent['payments_received']) }}</span>
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                        <td>
                            @if($dData['others']['cane_qty'] > 0 || $dData['others']['payments_received'] > 0)
                                <b>{{ $dData['others']['cane_qty'] }}</b>
                                @if($dData['others']['payments_received'] > 0)
                                    <br><span style="color:#15803d;font-size:5.8pt;">₹{{ round($dData['others']['payments_received']) }}</span>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td style="font-weight:bold;background-color:#f1f5f9;">
                            <b>{{ $dData['totals']['cane_qty'] }}</b>
                            @if($dData['totals']['filling_amount'] > 0)
                                <br><span style="font-size:5.8pt;color:#003366;">₹{{ number_format($dData['totals']['filling_amount'], 0) }}</span>
                            @endif
                        </td>
                    </tr>
                @endif
            @endfor

            {{-- Period 2 Summary Rows --}}
            <tr class="row-canes">
                <td colspan="2" style="text-align:right;">P2 TOTAL CANES</td>
                @foreach($dealers as $d)
                    <td>{{ $period2_summary['dealers'][$d['id']]['total_canes'] }}</td>
                @endforeach
                <td>{{ $period2_summary['others']['total_canes'] }}</td>
                <td>{{ $period2_summary['totals']['total_canes'] }}</td>
            </tr>
            <tr class="row-filling">
                <td colspan="2" style="text-align:right;">P2 FILLING (₹)</td>
                @foreach($dealers as $d)
                    <td>{{ number_format($period2_summary['dealers'][$d['id']]['total_filling'], 2) }}</td>
                @endforeach
                <td>{{ number_format($period2_summary['others']['total_filling'], 2) }}</td>
                <td>{{ number_format($period2_summary['totals']['total_filling'], 2) }}</td>
            </tr>
            <tr class="row-due">
                <td colspan="2" style="text-align:right;">P2 TOTAL DUE (₹)</td>
                @foreach($dealers as $d)
                    <td>{{ number_format($period2_summary['dealers'][$d['id']]['total_due'], 2) }}</td>
                @endforeach
                <td>{{ number_format($period2_summary['others']['total_due'], 2) }}</td>
                <td>{{ number_format($period2_summary['totals']['total_due'], 2) }}</td>
            </tr>
            <tr class="row-payments">
                <td colspan="2" style="text-align:right;">P2 PAYMENTS (₹)</td>
                @foreach($dealers as $d)
                    <td>{{ number_format($period2_summary['dealers'][$d['id']]['total_payments'], 2) }}</td>
                @endforeach
                <td>{{ number_format($period2_summary['others']['total_payments'], 2) }}</td>
                <td>{{ number_format($period2_summary['totals']['total_payments'], 2) }}</td>
            </tr>
            <tr class="row-closing">
                <td colspan="2" style="text-align:right;">P2 CLOSING BAL</td>
                @foreach($dealers as $d)
                    <td>{{ number_format($period2_summary['dealers'][$d['id']]['closing_balance'], 2) }}</td>
                @endforeach
                <td>{{ number_format($period2_summary['others']['closing_balance'], 2) }}</td>
                <td>{{ number_format($period2_summary['totals']['closing_balance'], 2) }}</td>
            </tr>

            {{-- MONTH GRAND TOTALS --}}
            <tr>
                <td colspan="{{ 3 + $dealerCount + 1 }}" style="background-color:#003366;color:#ffffff;font-weight:bold;font-size:8pt;text-align:center;padding:3px;">
                    ★ MONTH GRAND TOTALS ({{ strtoupper($report_meta['month_name']) }}) ★
                </td>
            </tr>
            <tr class="row-opening">
                <td colspan="2" style="text-align:right;">MONTH OPENING BAL</td>
                @foreach($dealers as $d)
                    <td>{{ number_format($month_summary['dealers'][$d['id']]['month_opening_balance'], 2) }}</td>
                @endforeach
                <td>{{ number_format($month_summary['others']['month_opening_balance'], 2) }}</td>
                <td>{{ number_format($month_summary['totals']['month_opening_balance'], 2) }}</td>
            </tr>
            <tr class="row-canes">
                <td colspan="2" style="text-align:right;">MONTH TOTAL CANES</td>
                @foreach($dealers as $d)
                    <td>{{ $month_summary['dealers'][$d['id']]['total_canes'] }}</td>
                @endforeach
                <td>{{ $month_summary['others']['total_canes'] }}</td>
                <td>{{ $month_summary['totals']['total_canes'] }}</td>
            </tr>
            <tr class="row-filling">
                <td colspan="2" style="text-align:right;">MONTH FILLING (₹)</td>
                @foreach($dealers as $d)
                    <td>{{ number_format($month_summary['dealers'][$d['id']]['total_filling'], 2) }}</td>
                @endforeach
                <td>{{ number_format($month_summary['others']['total_filling'], 2) }}</td>
                <td>{{ number_format($month_summary['totals']['total_filling'], 2) }}</td>
            </tr>
            <tr class="row-payments">
                <td colspan="2" style="text-align:right;">MONTH PAYMENTS (₹)</td>
                @foreach($dealers as $d)
                    <td>{{ number_format($month_summary['dealers'][$d['id']]['total_payments'], 2) }}</td>
                @endforeach
                <td>{{ number_format($month_summary['others']['total_payments'], 2) }}</td>
                <td>{{ number_format($month_summary['totals']['total_payments'], 2) }}</td>
            </tr>
            <tr class="row-closing">
                <td colspan="2" style="text-align:right;font-size:7.5pt;">MONTH CLOSING BAL</td>
                @foreach($dealers as $d)
                    <td style="font-size:7.5pt;">{{ number_format($month_summary['dealers'][$d['id']]['month_closing_balance'], 2) }}</td>
                @endforeach
                <td style="font-size:7.5pt;">{{ number_format($month_summary['others']['month_closing_balance'], 2) }}</td>
                <td style="font-size:8pt;font-weight:bold;background-color:#ffe4e6;color:#be123c;">{{ number_format($month_summary['totals']['month_closing_balance'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- LEGEND & PRODUCT BREAKDOWN --}}
    <table style="width:100%;margin-top:10px;">
        <tr>
            <td style="vertical-align:top;width:45%;">
                <div class="legend">
                    <b>COLOR-CODED MATRIX LEGEND:</b><br>
                    <span class="legend-box" style="background:#fef3c7;"></span> Opening Balance &nbsp;&nbsp;
                    <span class="legend-box" style="background:#e0f2fe;"></span> Daily Canes Delivered &nbsp;&nbsp;
                    <span class="legend-box" style="background:#e0e7ff;"></span> Filling / Supply Amount<br>
                    <span class="legend-box" style="background:#ffedd5;"></span> Total Amount Due &nbsp;&nbsp;
                    <span class="legend-box" style="background:#dcfce7;"></span> Payments Received &nbsp;&nbsp;
                    <span class="legend-box" style="background:#ffe4e6;"></span> Closing Balance<br>
                    <span style="color:#64748b;font-style:italic;">* P1 Closing Balance carries forward into P2 Opening Balance automatically.</span>
                </div>
            </td>
            <td style="vertical-align:top;width:55%;">
                @if(count($product_breakdown) > 0)
                    <table class="product-summary-table">
                        <thead>
                            <tr>
                                <th>Product Specification</th>
                                <th>Unit</th>
                                <th>Monthly Quantity</th>
                                <th>Total Value (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($product_breakdown as $pb)
                                <tr>
                                    <td>{{ $pb['name'] }} ({{ $pb['sku'] }})</td>
                                    <td style="text-align:center;">{{ $pb['unit'] }}</td>
                                    <td style="text-align:center;font-weight:bold;">{{ number_format($pb['total_quantity']) }}</td>
                                    <td style="text-align:right;">₹{{ number_format($pb['total_amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
