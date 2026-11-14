<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Damaged Products Report</title>
    <style>
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
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 10px;
        }
        .header {
            border-bottom: 2px solid #0284c7;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .company-name {
            font-size: 16pt;
            font-weight: bold;
            color: #0284c7;
            text-transform: uppercase;
        }
        .report-title {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 4px;
        }
        .meta-text {
            font-size: 8pt;
            color: #64748b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #0284c7;
            color: #ffffff;
            font-weight: bold;
            font-size: 8pt;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #0284c7;
        }
        td {
            padding: 5px 8px;
            font-size: 8pt;
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
        .badge {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 7.5pt;
            font-weight: bold;
        }
        .totals-row td {
            font-weight: bold;
            background-color: #e2e8f0 !important;
            border-top: 2px solid #0284c7;
            font-size: 8.5pt;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="border: none; padding: 0;">
                    <div class="company-name">{{ $settings->company_name ?? 'MARINA AQUA' }}</div>
                    <div class="report-title">DAMAGED PRODUCTS REPORT</div>
                    @if(!empty($filters['start_date']) && !empty($filters['end_date']))
                        <div class="meta-text">Period: {{ \Carbon\Carbon::parse($filters['start_date'])->format('d M Y') }} to {{ \Carbon\Carbon::parse($filters['end_date'])->format('d M Y') }}</div>
                    @else
                        <div class="meta-text">All Recorded Damage Out Transactions</div>
                    @endif
                </td>
                <td style="border: none; padding: 0; text-align: right; vertical-align: top;">
                    <div class="meta-text">Generated: {{ $generatedAt }}</div>
                    <div class="meta-text">Total Records: {{ count($entries) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;" class="text-center">#</th>
                <th style="width: 13%;">Damage No</th>
                <th style="width: 10%;">Date</th>
                <th style="width: 22%;">Product Name</th>
                <th style="width: 9%;">Variant</th>
                <th style="width: 8%;" class="text-right">Qty</th>
                <th style="width: 7%;" class="text-center">Unit</th>
                <th style="width: 12%;">Reason</th>
                <th style="width: 15%;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td style="font-weight: bold; color: #0284c7;">{{ $item->damage_no }}</td>
                <td>{{ \Carbon\Carbon::parse($item->damage_date)->format('d M Y') }}</td>
                <td><strong>{{ $item->product_name }}</strong></td>
                <td>{{ $item->variant ?? '-' }}</td>
                <td class="text-right" style="font-weight: bold; color: #b91c1c;">{{ number_format($item->quantity, 0) }}</td>
                <td class="text-center">{{ $item->unit }}</td>
                <td><span class="badge">{{ $item->reason }}</span></td>
                <td>{{ $item->remarks ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center" style="padding: 18px; color: #64748b;">No damaged product records found.</td>
            </tr>
            @endforelse
        </tbody>
        @if(count($entries) > 0)
        <tfoot>
            <tr class="totals-row">
                <td colspan="5" class="text-right">Total Damaged Quantity:</td>
                <td class="text-right" style="color: #b91c1c;">{{ number_format($totalQuantity, 0) }}</td>
                <td colspan="3">&nbsp;</td>
            </tr>
        </tfoot>
        @endif
    </table>
</body>
</html>
