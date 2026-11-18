<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Others Statement - {{ $month_name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 10mm 12mm 10mm;
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
            font-size: 9pt;
            color: #1a202c;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .header {
            text-align: center;
            margin-bottom: 12px;
            border-bottom: 2px solid #0f3460;
            padding-bottom: 8px;
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
            font-size: 16pt;
            font-weight: bold;
            color: #0f3460;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin: 0;
        }
        .report-subtitle {
            font-size: 11.5pt;
            font-weight: bold;
            color: #16213e;
            margin: 3px 0;
        }
        .meta-line {
            font-size: 9pt;
            color: #475569;
            font-weight: 500;
        }
        .table-panel {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9pt;
        }
        .table-panel th, .table-panel td {
            border: 1px solid #cbd5e1;
            padding: 7px 10px;
        }
        .table-panel th {
            background-color: #0f3460;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8.5pt;
            letter-spacing: 0.5px;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-left {
            text-align: left;
        }
        .row-even {
            background-color: #ffffff;
        }
        .row-odd {
            background-color: #f8fafc;
        }
        .total-row {
            background-color: #f1f5f9;
            font-weight: bold;
            border-top: 2px solid #0f3460;
            font-size: 9.5pt;
        }
        .summary-cards {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 12px;
        }
        .summary-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 14px;
            text-align: center;
        }
        .summary-card-label {
            font-size: 8pt;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .summary-card-val {
            font-size: 13pt;
            font-weight: bold;
            color: #0f3460;
            margin-top: 2px;
        }
        .footer-note {
            margin-top: 20px;
            font-size: 7.5pt;
            color: #94a3b8;
            text-align: center;
            border-top: 1px dashed #cbd5e1;
            padding-top: 8px;
        }
    </style>
</head>
<body>

    <div class="header">
        <table class="header-table">
            <tr>
                @if(!empty($company_logo))
                    <td style="width: 75px; text-align: left;">
                        <img src="{{ $company_logo }}" style="max-height: 48px; max-width: 70px; object-fit: contain;" alt="Logo">
                    </td>
                @endif
                <td style="text-align: {{ !empty($company_logo) ? 'left' : 'center' }}; padding-left: {{ !empty($company_logo) ? '12px' : '0' }};">
                    <div class="company-title">{{ $company_name }}</div>
                    <div class="report-subtitle">OTHERS STATEMENT</div>
                    <div class="meta-line">Period: {{ $month_name }} &bull; Generated on: {{ date('d-m-Y h:i A') }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Quick KPI summary cards --}}
    <table class="summary-cards">
        <tr>
            <td style="width: 50%; padding-right: 6px;">
                <div class="summary-card">
                    <div class="summary-card-label">Total Bottle Count</div>
                    <div class="summary-card-val">{{ number_format($total_bottle_count) }}</div>
                </div>
            </td>
            <td style="width: 50%; padding-left: 6px;">
                <div class="summary-card">
                    <div class="summary-card-label">Total Amount</div>
                    <div class="summary-card-val">₹{{ number_format($grand_total_amount, 2) }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Date-wise Statement Table --}}
    <table class="table-panel">
        <thead>
            <tr>
                <th class="text-center" style="width: 15%;">Sl. No.</th>
                <th class="text-center" style="width: 35%;">Date</th>
                <th class="text-right" style="width: 25%;">Bottle Count</th>
                <th class="text-right" style="width: 25%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($statement as $index => $row)
                <tr class="{{ $index % 2 === 0 ? 'row-even' : 'row-odd' }}">
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">{{ $row['formatted_date'] }} ({{ $row['day_name'] }})</td>
                    <td class="text-right">{{ number_format($row['bottle_count']) }}</td>
                    <td class="text-right">₹{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center" style="padding: 16px; color: #64748b;">
                        No Others entries recorded for {{ $month_name }}.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="text-center" style="letter-spacing: 0.5px;">TOTAL</td>
                <td class="text-right">{{ number_format($total_bottle_count) }}</td>
                <td class="text-right">₹{{ number_format($grand_total_amount, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer-note">
        This document is system-generated from Marina Aqua &bull; {{ $company_name }} &bull; Confirmed Entries
    </div>

</body>
</html>
