<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report_meta['title'] }} - {{ $report_meta['month_name'] }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 3mm 4mm 3mm 4mm;
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
            font-size: {{ !empty($is_detailed) ? '5.1pt' : '5.3pt' }};
            color: #1a202c;
            margin: 0;
            padding: 0;
            background: #fff;
            line-height: 1.0;
        }
        .header {
            text-align: center;
            margin-bottom: 2px;
            border-bottom: 1.2px solid #003366;
            padding-bottom: 1px;
        }
        .company-title {
            font-size: 10pt;
            font-weight: bold;
            color: #003366;
            letter-spacing: 0.3px;
            margin: 0;
            line-height: 1.05;
        }
        .report-subtitle {
            font-size: 7.2pt;
            font-weight: bold;
            color: #007791;
            margin: 0;
        }
        .meta-line {
            font-size: 5.2pt;
            color: #64748b;
        }
        table.matrix {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
            table-layout: fixed;
        }
        table.matrix th, table.matrix td {
            border: 0.4pt solid #cbd5e1;
            padding: {{ !empty($is_detailed) ? '0.6px 1px' : '0.6px 1.5px' }};
            text-align: center;
            font-size: {{ !empty($is_detailed) ? '5.1pt' : '5.3pt' }};
            overflow: hidden;
            word-wrap: break-word;
            line-height: 1.0;
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
            font-size: {{ !empty($is_detailed) ? '5.3pt' : '5.5pt' }};
            padding: 1px 1.5px;
        }
        .th-date {
            width: 44px;
        }
        .th-day {
            width: 22px;
        }
        .th-total {
            width: 52px;
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
        }
        .period-banner {
            background-color: #e2e8f0;
            font-weight: bold;
            font-size: {{ !empty($is_detailed) ? '5.6pt' : '6.2pt' }};
            color: #0f172a;
            text-align: left;
            padding-left: 4px;
            padding-top: 1px;
            padding-bottom: 1px;
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
            margin-top: 4px;
            font-size: 5.5pt;
            color: #475569;
        }
        .legend-box {
            display: inline-block;
            width: 7px;
            height: 7px;
            margin-right: 2px;
            vertical-align: middle;
            border: 0.4pt solid #94a3b8;
        }
        .product-summary-table {
            width: 100%;
            margin-top: 4px;
            border-collapse: collapse;
            font-size: 5.8pt;
        }
        .product-summary-table th, .product-summary-table td {
            border: 0.4pt solid #cbd5e1;
            padding: 1.5px 4px;
        }
        .product-summary-table th {
            background-color: #003366;
            color: #ffffff;
            font-weight: bold;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="company-title">{{ $company['name'] }}</h1>
        <div class="report-subtitle">{{ $report_meta['title'] }} — {{ strtoupper($report_meta['month_name']) }}</div>
        <div class="meta-line">
            {{ $company['address'] }} | GSTIN: {{ $company['gstin'] }} | Primary Metric: {{ $report_meta['cane_product']['name'] }} ({{ $report_meta['cane_product']['unit'] }})
        </div>
    </div>

    @php
        $dealerCount = count($dealers);
        $detailed = !empty($is_detailed);
    @endphp

    <table class="matrix">
        <thead>
            <tr>
                <th class="th-main th-date">Date</th>
                <th class="th-main th-day">Day</th>
                @foreach($dealers as $d)
                    <th class="th-main">{{ $d['name'] }}<br><span style="font-size:4.8pt;font-weight:normal;">({{ $d['code'] }})</span></th>
                @endforeach
                <th class="th-main" style="background-color:#1e293b;">Others<br><span style="font-size:4.8pt;font-weight:normal;">(Retail)</span></th>
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

            {{-- Period 1 Daily Rows (Days 1 to 15) --}}
            @for($day = 1; $day <= 15; $day++)
                @php $dData = $dates[$day - 1] ?? null; @endphp
                @if($dData)
                    <tr class="{{ $day % 2 == 0 ? 'row-even' : '' }}">
                        <td>{{ $dData['formatted'] }}</td>
                        <td style="{{ $dData['day_name'] === 'Sun' ? 'color:#ef4444;font-weight:bold;' : '' }}">{{ $dData['day_name'] }}</td>
                        @foreach($dealers as $d)
                            @php $ent = $dData['dealers'][$d['id']]; @endphp
                            <td>
                                @if($detailed)
                                    @if($ent['cane_qty'] > 0 || $ent['filling_amount'] > 0 || $ent['payments_received'] > 0)
                                        <b>{{ $ent['cane_qty'] }} cans</b>
                                        @if($ent['filling_amount'] > 0)
                                            <br><span style="color:#3730a3;font-size:4.8pt;">₹{{ round($ent['filling_amount']) }}</span>
                                        @endif
                                        @if($ent['payments_received'] > 0)
                                            <br><span style="color:#15803d;font-size:4.8pt;font-weight:bold;">₹{{ round($ent['payments_received']) }} pd</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                @else
                                    {{ $ent['cane_qty'] > 0 ? $ent['cane_qty'] : '-' }}
                                @endif
                            </td>
                        @endforeach
                        <td>
                            @if($detailed)
                                @if($dData['others']['cane_qty'] > 0 || $dData['others']['payments_received'] > 0)
                                    <b>{{ $dData['others']['cane_qty'] }} cans</b>
                                    @if($dData['others']['payments_received'] > 0)
                                        <br><span style="color:#15803d;font-size:4.8pt;font-weight:bold;">₹{{ round($dData['others']['payments_received']) }} pd</span>
                                    @endif
                                @else
                                    -
                                @endif
                            @else
                                {{ $dData['others']['cane_qty'] > 0 ? $dData['others']['cane_qty'] : '-' }}
                            @endif
                        </td>
                        <td style="font-weight:bold;background-color:#f1f5f9;">
                            @if($detailed)
                                <b>{{ $dData['totals']['cane_qty'] }} cans</b>
                                @if($dData['totals']['filling_amount'] > 0)
                                    <br><span style="font-size:4.8pt;color:#003366;">₹{{ number_format($dData['totals']['filling_amount'], 0) }}</span>
                                @endif
                            @else
                                {{ $dData['totals']['cane_qty'] > 0 ? $dData['totals']['cane_qty'].' cans' : '-' }}
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

            {{-- PERIOD 2 (Days 16 - End of Month) --}}
            <tr>
                <td colspan="{{ 3 + $dealerCount + 1 }}" class="period-banner" style="background-color:#cbd5e1;">
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
                        <td style="{{ $dData['day_name'] === 'Sun' ? 'color:#ef4444;font-weight:bold;' : '' }}">{{ $dData['day_name'] }}</td>
                        @foreach($dealers as $d)
                            @php $ent = $dData['dealers'][$d['id']]; @endphp
                            <td>
                                @if($detailed)
                                    @if($ent['cane_qty'] > 0 || $ent['filling_amount'] > 0 || $ent['payments_received'] > 0)
                                        <b>{{ $ent['cane_qty'] }} cans</b>
                                        @if($ent['filling_amount'] > 0)
                                            <br><span style="color:#3730a3;font-size:4.8pt;">₹{{ round($ent['filling_amount']) }}</span>
                                        @endif
                                        @if($ent['payments_received'] > 0)
                                            <br><span style="color:#15803d;font-size:4.8pt;font-weight:bold;">₹{{ round($ent['payments_received']) }} pd</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                @else
                                    {{ $ent['cane_qty'] > 0 ? $ent['cane_qty'] : '-' }}
                                @endif
                            </td>
                        @endforeach
                        <td>
                            @if($detailed)
                                @if($dData['others']['cane_qty'] > 0 || $dData['others']['payments_received'] > 0)
                                    <b>{{ $dData['others']['cane_qty'] }} cans</b>
                                    @if($dData['others']['payments_received'] > 0)
                                        <br><span style="color:#15803d;font-size:4.8pt;font-weight:bold;">₹{{ round($dData['others']['payments_received']) }} pd</span>
                                    @endif
                                @else
                                    -
                                @endif
                            @else
                                {{ $dData['others']['cane_qty'] > 0 ? $dData['others']['cane_qty'] : '-' }}
                            @endif
                        </td>
                        <td style="font-weight:bold;background-color:#f1f5f9;">
                            @if($detailed)
                                <b>{{ $dData['totals']['cane_qty'] }} cans</b>
                                @if($dData['totals']['filling_amount'] > 0)
                                    <br><span style="font-size:4.8pt;color:#003366;">₹{{ number_format($dData['totals']['filling_amount'], 0) }}</span>
                                @endif
                            @else
                                {{ $dData['totals']['cane_qty'] > 0 ? $dData['totals']['cane_qty'].' cans' : '-' }}
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

            @if(!$detailed)
                {{-- In Compact / Unchecked mode, Month Grand Totals fits directly on Page 1 --}}
                <tr>
                    <td colspan="{{ 3 + $dealerCount + 1 }}" style="background-color:#003366;color:#ffffff;font-weight:bold;font-size:6.5pt;text-align:center;padding:1px;">
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
                    <td colspan="2" style="text-align:right;font-size:6pt;">MONTH CLOSING BAL</td>
                    @foreach($dealers as $d)
                        <td style="font-size:6pt;">{{ number_format($month_summary['dealers'][$d['id']]['month_closing_balance'], 2) }}</td>
                    @endforeach
                    <td style="font-size:6pt;">{{ number_format($month_summary['others']['month_closing_balance'], 2) }}</td>
                    <td style="font-size:6.2pt;font-weight:bold;background-color:#ffe4e6;color:#be123c;">{{ number_format($month_summary['totals']['month_closing_balance'], 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    @if($detailed)
        {{-- In Detailed mode, Month Grand Totals and Product Summary start on Page 2 if needed --}}
        <div class="page-break" style="margin-top:6px;">
            <div class="header">
                <h2 class="company-title" style="font-size:10pt;">{{ $company['name'] }} — Monthly Summary & Product Breakdown</h2>
                <div class="meta-line">{{ strtoupper($report_meta['month_name']) }} | Dealer Ledger Summary</div>
            </div>

            <table class="matrix" style="margin-top:4px;">
                <thead>
                    <tr>
                        <th class="th-main th-date" colspan="2">Metric</th>
                        @foreach($dealers as $d)
                            <th class="th-main">{{ $d['name'] }}<br><span style="font-size:4.8pt;font-weight:normal;">({{ $d['code'] }})</span></th>
                        @endforeach
                        <th class="th-main" style="background-color:#1e293b;">Others</th>
                        <th class="th-main th-total">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="{{ 3 + $dealerCount + 1 }}" style="background-color:#003366;color:#ffffff;font-weight:bold;font-size:6.5pt;text-align:center;padding:2px;">
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
                        <td colspan="2" style="text-align:right;font-size:6.2pt;">MONTH CLOSING BAL</td>
                        @foreach($dealers as $d)
                            <td style="font-size:6.2pt;">{{ number_format($month_summary['dealers'][$d['id']]['month_closing_balance'], 2) }}</td>
                        @endforeach
                        <td style="font-size:6.2pt;">{{ number_format($month_summary['others']['month_closing_balance'], 2) }}</td>
                        <td style="font-size:6.5pt;font-weight:bold;background-color:#ffe4e6;color:#be123c;">{{ number_format($month_summary['totals']['month_closing_balance'], 2) }}</td>
                    </tr>
                </tbody>
            </table>

            {{-- Product Breakdown Table --}}
            @if(count($product_breakdown) > 0)
                <div style="margin-top:10px;">
                    <div style="font-weight:bold;font-size:7pt;color:#003366;margin-bottom:2px;">Product-Wise Detailed Summary:</div>
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
                </div>
            @endif
        </div>
    @endif
</body>
</html>
