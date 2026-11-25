<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Public Sales Monthly Statement - {{ $statement['party']['party_name'] }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #1e293b;
            line-height: 1.35;
            margin: 0;
            padding: 8px 12px;
        }
        .header {
            border-bottom: 2px solid #059669;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .company-name {
            font-size: 15pt;
            font-weight: bold;
            color: #059669;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .company-meta {
            font-size: 7.5pt;
            color: #475569;
            margin-top: 2px;
        }
        .report-title {
            font-size: 12pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 4px;
        }
        .party-box {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 10px;
        }
        .party-title {
            font-size: 9pt;
            font-weight: bold;
            color: #166534;
        }
        .party-meta {
            font-size: 7.5pt;
            color: #15803d;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        th {
            background-color: #059669;
            color: #ffffff;
            font-weight: bold;
            font-size: 7.5pt;
            text-align: center;
            padding: 5px 4px;
            border: 1px solid #059669;
        }
        td {
            padding: 4px 6px;
            font-size: 7.5pt;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .text-left {
            text-align: left;
        }
        .totals-row td {
            font-weight: bold;
            background-color: #d1fae5 !important;
            border-top: 2px solid #059669;
            color: #065f46;
            font-size: 8pt;
        }
        .footer {
            margin-top: 14px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 6px;
            font-size: 7pt;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%; border: none; margin: 0;">
            <tr>
                <td style="border: none; padding: 0; vertical-align: top; width: 65%;">
                    <div class="company-name">{{ $statement['company']['name'] }}</div>
                    @if(!empty($statement['company']['tagline']))
                        <div class="company-meta" style="font-weight: bold;">{{ $statement['company']['tagline'] }}</div>
                    @endif
                    <div class="company-meta">
                        {{ $statement['company']['address'] }}
                        @if(!empty($statement['company']['phone'])) &bull; Tel: {{ $statement['company']['phone'] }} @endif
                        @if(!empty($statement['company']['gstin'])) &bull; GSTIN: {{ $statement['company']['gstin'] }} @endif
                    </div>
                </td>
                <td style="border: none; padding: 0; vertical-align: top; text-align: right; width: 35%;">
                    <div class="report-title">PUBLIC SALES STATEMENT</div>
                    <div class="company-meta">Month: <strong>{{ $statement['period']['month_name'] }}</strong></div>
                    <div class="company-meta">Generated: {{ $generatedAt }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="party-box">
        <table style="width: 100%; border: none; margin: 0;">
            <tr>
                <td style="border: none; padding: 0;">
                    <span class="party-title">Party Name: {{ $statement['party']['party_name'] }}</span>
                    @if(!empty($statement['party']['contact_number']))
                        <span class="party-meta" style="margin-left: 14px;">Contact: {{ $statement['party']['contact_number'] }}</span>
                    @endif
                    @if(!empty($statement['party']['address']))
                        <div class="party-meta" style="margin-top: 2px;">Address: {{ $statement['party']['address'] }}</div>
                    @endif
                </td>
                <td style="border: none; padding: 0; text-align: right;">
                    <span class="party-meta">Total Days with Sales: <strong>{{ count($statement['daily_records']) }}</strong> | Total Entries: <strong>{{ $statement['monthly_totals']['total_entries'] }}</strong></span>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 80px;" class="text-left">Date</th>
                <th style="width: 45px;">Entries</th>
                @foreach($statement['product_columns'] as $col)
                    <th>{{ $col }}</th>
                @endforeach
                <th style="width: 75px;" class="text-right">Cash (₹)</th>
                <th style="width: 75px;" class="text-right">UPI (₹)</th>
                <th style="width: 85px;" class="text-right">Daily Total (₹)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($statement['daily_records'] as $record)
                <tr>
                    <td class="text-left">{{ $record['formatted_date'] }}</td>
                    <td class="text-center">{{ $record['entries_count'] }}</td>
                    @foreach($statement['product_columns'] as $col)
                        <td class="text-center">
                            {{ isset($record['product_quantities'][$col]) && $record['product_quantities'][$col] > 0 ? (int)$record['product_quantities'][$col] == $record['product_quantities'][$col] ? (int)$record['product_quantities'][$col] : number_format($record['product_quantities'][$col], 1) : '-' }}
                        </td>
                    @endforeach
                    <td class="text-right">₹{{ number_format($record['cash_amount'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($record['upi_amount'], 2) }}</td>
                    <td class="text-right" style="font-weight: bold;">₹{{ number_format($record['daily_total'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($statement['product_columns']) + 5 }}" class="text-center" style="padding: 16px; color: #94a3b8;">
                        No public sales entries recorded for {{ $statement['party']['party_name'] }} in {{ $statement['period']['month_name'] }}.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(!empty($statement['daily_records']))
            <tfoot>
                <tr class="totals-row">
                    <td class="text-left">MONTHLY TOTAL</td>
                    <td class="text-center">{{ $statement['monthly_totals']['total_entries'] }}</td>
                    @foreach($statement['product_columns'] as $col)
                        <td class="text-center">
                            {{ isset($statement['monthly_totals']['product_quantities'][$col]) ? ((int)$statement['monthly_totals']['product_quantities'][$col] == $statement['monthly_totals']['product_quantities'][$col] ? (int)$statement['monthly_totals']['product_quantities'][$col] : number_format($statement['monthly_totals']['product_quantities'][$col], 1)) : 0 }}
                        </td>
                    @endforeach
                    <td class="text-right">₹{{ number_format($statement['monthly_totals']['total_cash'], 2) }}</td>
                    <td class="text-right">₹{{ number_format($statement['monthly_totals']['total_upi'], 2) }}</td>
                    <td class="text-right" style="font-size: 8.5pt;">₹{{ number_format($statement['monthly_totals']['grand_total'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">
        This document is system-generated by {{ $statement['company']['name'] }} &bull; Public Sales Module &bull; Powered by DharviCloud
    </div>
</body>
</html>
