<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report_meta['title'] }} - {{ $report_meta['month_name'] }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 4mm 5mm 4mm 5mm;
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
            font-size: {{ !empty($is_detailed) ? '6.5pt' : '7.2pt' }};
            color: #1a202c;
            margin: 0;
            padding: 0;
            background: #fff;
            line-height: 1.15;
        }
        .header {
            text-align: center;
            margin-bottom: 3px;
            border-bottom: 1.5px solid #003366;
            padding-bottom: 2px;
        }
        .company-title {
            font-size: 13pt;
            font-weight: bold;
            color: #003366;
            letter-spacing: 0.5px;
            margin: 0 0 1px 0;
            line-height: 1.1;
        }
        .report-subtitle {
            font-size: 8.5pt;
            font-weight: bold;
            color: #007791;
            margin: 0 0 1px 0;
            letter-spacing: 0.3px;
        }
        .meta-line {
            font-size: 6.5pt;
            color: #475569;
            margin: 0;
        }
        table.matrix {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
            table-layout: fixed;
        }
        table.matrix th, table.matrix td {
            border: 0.5pt solid #cbd5e1;
            padding: {{ !empty($is_detailed) ? '1.5pt 1.5pt' : '2.2pt 2.5pt' }};
            text-align: center;
            font-size: {{ !empty($is_detailed) ? '6.5pt' : '7.2pt' }};
            overflow: hidden;
            word-wrap: break-word;
            line-height: 1.15;
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
            font-size: {{ !empty($is_detailed) ? '6.8pt' : '7.5pt' }};
            padding: 2.5pt 2pt;
        }
        .th-date {
            width: 58px;
        }
        .th-day {
            width: 32px;
        }
        .th-total {
            width: 72px;
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
        }
        .period-banner {
            background-color: #e2e8f0;
            font-weight: bold;
            font-size: {{ !empty($is_detailed) ? '7.2pt' : '7.8pt' }};
            color: #0f172a;
            text-align: left;
            padding-left: 6px;
            padding-top: 2pt;
            padding-bottom: 2pt;
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
        .page-footer {
            margin-top: 4px;
            border-top: 0.5pt solid #cbd5e1;
            padding-top: 2px;
        }
        .product-summary-table {
            width: 100%;
            margin-top: 6px;
            border-collapse: collapse;
            font-size: 7pt;
        }
        .product-summary-table th, .product-summary-table td {
            border: 0.5pt solid #cbd5e1;
            padding: 2.5pt 5pt;
        }
        .product-summary-table th {
            background-color: #003366;
            color: #ffffff;
            font-weight: bold;
        }
        .page-break {
            page-break-before: always !important;
            break-before: page !important;
            height: 0;
            line-height: 0;
        }
    </style>
</head>
<body>
    @php
        $dealerCount = count($dealers);
        $detailed = !empty($is_detailed);
        $daysInMonth = (int) ($report_meta['days_in_month'] ?? 30);
    @endphp

    {{-- ========================================================================= --}}
    {{-- PAGE 1 : DAYS 01 - 15 (PERIOD 1)                                          --}}
    {{-- ========================================================================= --}}
    <div class="page-section">
        <div class="header">
            <h1 class="company-title">{{ $company['name'] }}</h1>
            <div class="report-subtitle">{{ $report_meta['title'] }} — {{ strtoupper($report_meta['month_name']) }} &nbsp;|&nbsp; PART 1 : DAYS 01 – 15</div>
            <div class="meta-line">
                {{ $company['address'] }} | GSTIN: {{ $company['gstin'] }} | Primary Metric: {{ $report_meta['cane_product']['name'] }} ({{ $report_meta['cane_product']['unit'] }}) | Currency: INR (₹)@if($detailed) | <b>Payment Colors:</b> <span style="color:#15803d;font-weight:bold;">Cash (Green)</span> &bull; <span style="color:#7e22ce;font-weight:bold;">UPI (Purple)</span> &bull; <span style="color:#0f172a;font-weight:bold;">Card/Other (Black)</span>@endif
            </div>
        </div>

        <table class="matrix">
            <thead>
                <tr>
                    <th class="th-main th-date">Date</th>
                    <th class="th-main th-day">Day</th>
                    @foreach($dealers as $d)
                        <th class="th-main">{{ $d['name'] }}<br><span style="font-size:6.2pt;font-weight:normal;">({{ $d['code'] }})</span></th>
                    @endforeach
                    <th class="th-main" style="background-color:#1e293b;">Others<br><span style="font-size:6.2pt;font-weight:normal;">(Retail)</span></th>
                    <th class="th-main th-total">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                {{-- PERIOD 1 BANNER --}}
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
                                                <br><span style="color:#3730a3;font-size:6.0pt;">₹{{ round($ent['filling_amount']) }}</span>
                                            @endif
                                            @if(!empty($ent['payment_breakdown']))
                                                @foreach($ent['payment_breakdown'] as $pb)
                                                    <br><span style="color:{{ $pb['color'] }};font-size:5.8pt;font-weight:bold;white-space:nowrap;">{{ $pb['method'] }}: ₹{{ number_format(round($pb['amount'])) }}</span>
                                                @endforeach
                                            @elseif($ent['payments_received'] > 0)
                                                <br><span style="color:#15803d;font-size:5.8pt;font-weight:bold;white-space:nowrap;">Cash: ₹{{ round($ent['payments_received']) }}</span>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    @else
                                        @if($ent['cane_qty'] > 0 || $ent['payments_received'] > 0)
                                            {{ $ent['cane_qty'] > 0 ? $ent['cane_qty'] : '' }}
                                            @if(!empty($ent['payment_breakdown']))
                                                @foreach($ent['payment_breakdown'] as $pb)
                                                    <br><span style="color:{{ $pb['color'] }};font-size:5.8pt;font-weight:bold;white-space:nowrap;">{{ $pb['method'] }}: ₹{{ number_format(round($pb['amount'])) }}</span>
                                                @endforeach
                                            @elseif($ent['payments_received'] > 0)
                                                <br><span style="color:#15803d;font-size:5.8pt;font-weight:bold;white-space:nowrap;">Cash: ₹{{ round($ent['payments_received']) }}</span>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                @if($detailed)
                                    @if($dData['others']['cane_qty'] > 0 || $dData['others']['payments_received'] > 0)
                                        <b>{{ $dData['others']['cane_qty'] }} cans</b>
                                        @if(!empty($dData['others']['payment_breakdown']))
                                            @foreach($dData['others']['payment_breakdown'] as $pb)
                                                <br><span style="color:{{ $pb['color'] }};font-size:5.8pt;font-weight:bold;white-space:nowrap;">{{ $pb['method'] }}: ₹{{ number_format(round($pb['amount'])) }}</span>
                                            @endforeach
                                        @elseif($dData['others']['payments_received'] > 0)
                                            <br><span style="color:#15803d;font-size:5.8pt;font-weight:bold;white-space:nowrap;">Cash: ₹{{ round($dData['others']['payments_received']) }}</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                @else
                                    @if($dData['others']['cane_qty'] > 0 || $dData['others']['payments_received'] > 0)
                                        {{ $dData['others']['cane_qty'] > 0 ? $dData['others']['cane_qty'] : '' }}
                                        @if(!empty($dData['others']['payment_breakdown']))
                                            @foreach($dData['others']['payment_breakdown'] as $pb)
                                                <br><span style="color:{{ $pb['color'] }};font-size:5.8pt;font-weight:bold;white-space:nowrap;">{{ $pb['method'] }}: ₹{{ number_format(round($pb['amount'])) }}</span>
                                            @endforeach
                                        @elseif($dData['others']['payments_received'] > 0)
                                            <br><span style="color:#15803d;font-size:5.8pt;font-weight:bold;white-space:nowrap;">Cash: ₹{{ round($dData['others']['payments_received']) }}</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                @endif
                            </td>
                            <td style="font-weight:bold;background-color:#f1f5f9;">
                                @if($detailed)
                                    <b>{{ $dData['totals']['cane_qty'] }} cans</b>
                                    @if($dData['totals']['filling_amount'] > 0)
                                        <br><span style="font-size:6.0pt;color:#003366;">₹{{ number_format($dData['totals']['filling_amount'], 0) }}</span>
                                    @endif
                                    @if(!empty($dData['totals']['payment_breakdown']))
                                        @foreach($dData['totals']['payment_breakdown'] as $pb)
                                            <br><span style="color:{{ $pb['color'] }};font-size:5.8pt;font-weight:bold;white-space:nowrap;">{{ $pb['method'] }}: ₹{{ number_format(round($pb['amount'])) }}</span>
                                        @endforeach
                                    @elseif($dData['totals']['payments_received'] > 0)
                                        <br><span style="color:#15803d;font-size:5.8pt;font-weight:bold;">₹{{ number_format($dData['totals']['payments_received'], 0) }}</span>
                                    @endif
                                @else
                                    {{ $dData['totals']['cane_qty'] > 0 ? $dData['totals']['cane_qty'].' cans' : '-' }}
                                    @if(!empty($dData['totals']['payment_breakdown']))
                                        @foreach($dData['totals']['payment_breakdown'] as $pb)
                                            <br><span style="color:{{ $pb['color'] }};font-size:5.8pt;font-weight:bold;white-space:nowrap;">{{ $pb['method'] }}: ₹{{ number_format(round($pb['amount'])) }}</span>
                                        @endforeach
                                    @endif
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
            </tbody>
        </table>

        <div class="page-footer">
            <table style="width:100%;font-size:6.8pt;color:#64748b;">
                <tr>
                    <td style="text-align:left;font-weight:bold;color:#003366;">Page 1 of 2 — Period 1 (Days 01–15)</td>
                    <td style="text-align:center;">{{ $company['name'] }} &bull; {{ $report_meta['title'] }}</td>
                    <td style="text-align:right;font-weight:bold;color:#007791;">Continued on Page 2 (Days 16–{{ $daysInMonth }}) ▶</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- PAGE BREAK BETWEEN 15-DAY SECTIONS                                        --}}
    {{-- ========================================================================= --}}
    <div class="page-break"></div>

    {{-- ========================================================================= --}}
    {{-- PAGE 2 : DAYS 16 - END OF MONTH (PERIOD 2 & MONTH GRAND TOTALS)           --}}
    {{-- ========================================================================= --}}
    <div class="page-section">
        <div class="header">
            <h1 class="company-title">{{ $company['name'] }}</h1>
            <div class="report-subtitle">{{ $report_meta['title'] }} — {{ strtoupper($report_meta['month_name']) }} &nbsp;|&nbsp; PART 2 : DAYS 16 – {{ $daysInMonth }}</div>
            <div class="meta-line">
                {{ $company['address'] }} | GSTIN: {{ $company['gstin'] }} | Primary Metric: {{ $report_meta['cane_product']['name'] }} ({{ $report_meta['cane_product']['unit'] }}) | Currency: INR (₹)@if($detailed) | <b>Payment Colors:</b> <span style="color:#15803d;font-weight:bold;">Cash (Green)</span> &bull; <span style="color:#7e22ce;font-weight:bold;">UPI (Purple)</span> &bull; <span style="color:#0f172a;font-weight:bold;">Card/Other (Black)</span>@endif
            </div>
        </div>

        <table class="matrix">
            <thead>
                <tr>
                    <th class="th-main th-date">Date</th>
                    <th class="th-main th-day">Day</th>
                    @foreach($dealers as $d)
                        <th class="th-main">{{ $d['name'] }}<br><span style="font-size:6.2pt;font-weight:normal;">({{ $d['code'] }})</span></th>
                    @endforeach
                    <th class="th-main" style="background-color:#1e293b;">Others<br><span style="font-size:6.2pt;font-weight:normal;">(Retail)</span></th>
                    <th class="th-main th-total">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                {{-- PERIOD 2 BANNER --}}
                <tr>
                    <td colspan="{{ 3 + $dealerCount + 1 }}" class="period-banner" style="background-color:#cbd5e1;">
                        ▶ PERIOD 2 : DAYS 16 - {{ $daysInMonth }} ({{ strtoupper($report_meta['month_name']) }})
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

                {{-- Period 2 Daily Rows (Days 16 to $daysInMonth) --}}
                @for($day = 16; $day <= $daysInMonth; $day++)
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
                                                <br><span style="color:#3730a3;font-size:6.0pt;">₹{{ round($ent['filling_amount']) }}</span>
                                            @endif
                                            @if(!empty($ent['payment_breakdown']))
                                                @foreach($ent['payment_breakdown'] as $pb)
                                                    <br><span style="color:{{ $pb['color'] }};font-size:5.8pt;font-weight:bold;white-space:nowrap;">{{ $pb['method'] }}: ₹{{ number_format(round($pb['amount'])) }}</span>
                                                @endforeach
                                            @elseif($ent['payments_received'] > 0)
                                                <br><span style="color:#15803d;font-size:5.8pt;font-weight:bold;white-space:nowrap;">Cash: ₹{{ round($ent['payments_received']) }}</span>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    @else
                                        @if($ent['cane_qty'] > 0 || $ent['payments_received'] > 0)
                                            {{ $ent['cane_qty'] > 0 ? $ent['cane_qty'] : '' }}
                                            @if(!empty($ent['payment_breakdown']))
                                                @foreach($ent['payment_breakdown'] as $pb)
                                                    <br><span style="color:{{ $pb['color'] }};font-size:5.8pt;font-weight:bold;white-space:nowrap;">{{ $pb['method'] }}: ₹{{ number_format(round($pb['amount'])) }}</span>
                                                @endforeach
                                            @elseif($ent['payments_received'] > 0)
                                                <br><span style="color:#15803d;font-size:5.8pt;font-weight:bold;white-space:nowrap;">Cash: ₹{{ round($ent['payments_received']) }}</span>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                @if($detailed)
                                    @if($dData['others']['cane_qty'] > 0 || $dData['others']['payments_received'] > 0)
                                        <b>{{ $dData['others']['cane_qty'] }} cans</b>
                                        @if(!empty($dData['others']['payment_breakdown']))
                                            @foreach($dData['others']['payment_breakdown'] as $pb)
                                                <br><span style="color:{{ $pb['color'] }};font-size:5.8pt;font-weight:bold;white-space:nowrap;">{{ $pb['method'] }}: ₹{{ number_format(round($pb['amount'])) }}</span>
                                            @endforeach
                                        @elseif($dData['others']['payments_received'] > 0)
                                            <br><span style="color:#15803d;font-size:5.8pt;font-weight:bold;white-space:nowrap;">Cash: ₹{{ round($dData['others']['payments_received']) }}</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                @else
                                    @if($dData['others']['cane_qty'] > 0 || $dData['others']['payments_received'] > 0)
                                        {{ $dData['others']['cane_qty'] > 0 ? $dData['others']['cane_qty'] : '' }}
                                        @if(!empty($dData['others']['payment_breakdown']))
                                            @foreach($dData['others']['payment_breakdown'] as $pb)
                                                <br><span style="color:{{ $pb['color'] }};font-size:5.8pt;font-weight:bold;white-space:nowrap;">{{ $pb['method'] }}: ₹{{ number_format(round($pb['amount'])) }}</span>
                                            @endforeach
                                        @elseif($dData['others']['payments_received'] > 0)
                                            <br><span style="color:#15803d;font-size:5.8pt;font-weight:bold;white-space:nowrap;">Cash: ₹{{ round($dData['others']['payments_received']) }}</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                @endif
                            </td>
                            <td style="font-weight:bold;background-color:#f1f5f9;">
                                @if($detailed)
                                    <b>{{ $dData['totals']['cane_qty'] }} cans</b>
                                    @if($dData['totals']['filling_amount'] > 0)
                                        <br><span style="font-size:6.0pt;color:#003366;">₹{{ number_format($dData['totals']['filling_amount'], 0) }}</span>
                                    @endif
                                    @if(!empty($dData['totals']['payment_breakdown']))
                                        @foreach($dData['totals']['payment_breakdown'] as $pb)
                                            <br><span style="color:{{ $pb['color'] }};font-size:5.8pt;font-weight:bold;white-space:nowrap;">{{ $pb['method'] }}: ₹{{ number_format(round($pb['amount'])) }}</span>
                                        @endforeach
                                    @elseif($dData['totals']['payments_received'] > 0)
                                        <br><span style="color:#15803d;font-size:5.8pt;font-weight:bold;">₹{{ number_format($dData['totals']['payments_received'], 0) }}</span>
                                    @endif
                                @else
                                    {{ $dData['totals']['cane_qty'] > 0 ? $dData['totals']['cane_qty'].' cans' : '-' }}
                                    @if(!empty($dData['totals']['payment_breakdown']))
                                        @foreach($dData['totals']['payment_breakdown'] as $pb)
                                            <br><span style="color:{{ $pb['color'] }};font-size:5.8pt;font-weight:bold;white-space:nowrap;">{{ $pb['method'] }}: ₹{{ number_format(round($pb['amount'])) }}</span>
                                        @endforeach
                                    @endif
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

                {{-- ========================================================= --}}
                {{-- MONTH GRAND TOTALS (Displayed on final page)              --}}
                {{-- ========================================================= --}}
                <tr>
                    <td colspan="{{ 3 + $dealerCount + 1 }}" style="background-color:#003366;color:#ffffff;font-weight:bold;font-size:7.5pt;text-align:center;padding:2.5pt;">
                        ★ MONTH GRAND TOTALS ({{ strtoupper($report_meta['month_name']) }}) ★
                    </td>
                </tr>
                <tr class="row-opening">
                    <td colspan="2" style="text-align:right;">MONTH OPENING BAL</td>
                    @foreach($dealers as $d)
                        <td>{{ number_format($month_summary['dealers'][$d['id']]['month_opening_balance'], 2) }}</td>
                    @endforeach
                    <td>{{ number_format($month_summary['others']['month_opening_balance'], 2) }}</td>
                    <td style="font-weight:bold;">{{ number_format($month_summary['totals']['month_opening_balance'], 2) }}</td>
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
                    <td style="font-size:7.8pt;font-weight:bold;background-color:#ffe4e6;color:#be123c;">{{ number_format($month_summary['totals']['month_closing_balance'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="page-footer">
            <table style="width:100%;font-size:6.8pt;color:#64748b;">
                <tr>
                    <td style="text-align:left;font-weight:bold;color:#003366;">Page 2 of 2 — Period 2 (Days 16–{{ $daysInMonth }}) &amp; Monthly Summary</td>
                    <td style="text-align:center;">{{ $company['name'] }} &bull; {{ $report_meta['title'] }}</td>
                    <td style="text-align:right;font-weight:bold;color:#15803d;">✔ Monthly Ledger Complete</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Product Breakdown Table (if detailed mode has items) --}}
    @if($detailed && count($product_breakdown) > 0)
        <div class="page-break"></div>
        <div class="page-section">
            <div class="header">
                <h2 class="company-title" style="font-size:12pt;">{{ $company['name'] }} — Product Breakdown Summary</h2>
                <div class="meta-line">{{ strtoupper($report_meta['month_name']) }} | Product-Wise Distribution Metrics</div>
            </div>

            <table class="product-summary-table">
                <thead>
                    <tr>
                        <th style="text-align:left;">Product Specification</th>
                        <th>Unit</th>
                        <th>Monthly Quantity</th>
                        <th>Total Value (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($product_breakdown as $pb)
                        <tr>
                            <td style="text-align:left;">{{ $pb['name'] }} ({{ $pb['sku'] }})</td>
                            <td style="text-align:center;">{{ $pb['unit'] }}</td>
                            <td style="text-align:center;font-weight:bold;">{{ number_format($pb['total_quantity']) }}</td>
                            <td style="text-align:right;">₹{{ number_format($pb['total_amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
