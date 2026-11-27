<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Daily Sales Details - {{ $details['party']['party_name'] }} ({{ $details['formatted_date'] }})</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "DejaVu Sans", Arial, sans-serif;
            font-size: 8.5pt;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            background-color: #f1f5f9;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .toolbar {
            max-width: 210mm;
            margin: 0 auto 14px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 16px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
        }
        .toolbar-info {
            font-size: 13px;
            color: #334155;
            font-weight: 600;
        }
        .toolbar-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary {
            background-color: #059669;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #047857;
        }
        .btn-secondary {
            background-color: #e2e8f0;
            color: #334155;
        }
        .btn-secondary:hover {
            background-color: #cbd5e1;
        }
        .sheet {
            max-width: 210mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 10mm 12mm;
            border-radius: 6px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
        }
        .header {
            border-bottom: 2px solid #059669;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .company-name {
            font-size: 16pt;
            font-weight: bold;
            color: #059669;
            text-transform: uppercase;
        }
        .company-meta {
            font-size: 8pt;
            color: #475569;
            margin-top: 2px;
        }
        .report-title {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 4px;
        }
        .party-card {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 12px;
        }
        .party-title {
            font-size: 9.5pt;
            font-weight: bold;
            color: #166534;
        }
        .party-meta {
            font-size: 8pt;
            color: #15803d;
        }
        .entry-block {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            margin-bottom: 10px;
            background-color: #ffffff;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .entry-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 6px 10px;
            font-size: 8pt;
            font-weight: bold;
            color: #334155;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            display: table-header-group;
        }
        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: bold;
            font-size: 7.5pt;
            text-align: left;
            padding: 5px 8px;
            border-bottom: 1px solid #cbd5e1;
        }
        td {
            padding: 5px 8px;
            font-size: 8pt;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .summary-card {
            background-color: #d1fae5;
            border: 1.5px solid #059669;
            border-radius: 4px;
            padding: 10px 14px;
            margin-top: 14px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .summary-title {
            font-size: 10pt;
            font-weight: bold;
            color: #065f46;
            margin-bottom: 6px;
        }
        .footer {
            margin-top: 16px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 6px;
            font-size: 7pt;
            color: #64748b;
            text-align: center;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            .sheet {
                max-width: 100%;
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    @php
        $finalLogo = null;
        $candidateLogo = !empty($companyLogoPath) ? $companyLogoPath : (!empty($companyLogo) ? $companyLogo : null);
        if (!empty($candidateLogo) && file_exists($candidateLogo)) {
            $mime = @mime_content_type($candidateLogo) ?: 'image/png';
            $finalLogo = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($candidateLogo));
        }
    @endphp

    <div class="toolbar no-print">
        <div class="toolbar-info">
            Daily Sales Details &bull; {{ $details['party']['party_name'] }} ({{ $details['formatted_date'] }})
        </div>
        <div class="toolbar-actions">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                Print / Save as PDF
            </button>
            <button type="button" class="btn btn-secondary" onclick="window.close()">
                Close
            </button>
        </div>
    </div>

    <div class="sheet">
        <div class="header">
            <table style="width: 100%; border: none; margin: 0; border-collapse: collapse;">
                <tr>
                    @if(!empty($finalLogo))
                        <td style="width: 75px; vertical-align: middle; border: none; padding: 0 12px 0 0;">
                            <img src="{{ $finalLogo }}" style="max-height: 50px; max-width: 75px; object-fit: contain; display: block;" alt="Logo" />
                        </td>
                    @endif
                    <td style="border: none; padding: 0; vertical-align: top; width: {{ !empty($finalLogo) ? '55%' : '65%' }};">
                        <div class="company-name">{{ $details['company']['name'] }}</div>
                        @if(!empty($details['company']['tagline']))
                            <div class="company-meta" style="font-weight: bold;">{{ $details['company']['tagline'] }}</div>
                        @endif
                        <div class="company-meta">
                            {{ $details['company']['address'] }}
                            @if(!empty($details['company']['phone'])) &bull; Tel: {{ $details['company']['phone'] }} @endif
                        </div>
                    </td>
                    <td style="border: none; padding: 0; vertical-align: top; text-align: right; width: 35%;">
                        <div class="report-title">DAILY SALES DETAILS</div>
                        <div class="company-meta">Date: <strong>{{ $details['formatted_date'] }}</strong></div>
                        <div class="company-meta">Generated: {{ $generatedAt }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="party-card">
            <span class="party-title">Party: {{ $details['party']['party_name'] }}</span>
            @if(!empty($details['party']['contact_number']))
                <span class="party-meta" style="margin-left: 14px;">Contact: {{ $details['party']['contact_number'] }}</span>
            @endif
            @if(!empty($details['party']['address']))
                <span class="party-meta" style="margin-left: 14px;">Address: {{ $details['party']['address'] }}</span>
            @endif
        </div>

        @forelse($details['entries'] as $index => $entry)
            <div class="entry-block">
                <div class="entry-header">
                    <table style="width: 100%; border: none; margin: 0;">
                        <tr>
                            <td style="border: none; padding: 0;">
                                Entry #{{ $index + 1 }} &bull; ID: <strong>{{ $entry['entry_no'] }}</strong> &bull; Time: <strong>{{ $entry['exact_time'] }}</strong>
                            </td>
                            <td style="border: none; padding: 0; text-align: right;">
                                Cash: <strong>&#8377;{{ number_format($entry['cash_amount'], 2) }}</strong> | UPI: <strong>&#8377;{{ number_format($entry['upi_amount'], 2) }}</strong> | Total: <strong>&#8377;{{ number_format($entry['total_amount'], 2) }}</strong>
                            </td>
                        </tr>
                    </table>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;" class="text-center">#</th>
                            <th>Product Description</th>
                            <th style="width: 70px;" class="text-center">Unit</th>
                            <th style="width: 80px;" class="text-right">Rate (&#8377;)</th>
                            <th style="width: 80px;" class="text-center">Qty</th>
                            <th style="width: 100px;" class="text-right">Amount (&#8377;)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entry['items'] as $itemIdx => $item)
                            <tr>
                                <td class="text-center">{{ $itemIdx + 1 }}</td>
                                <td><strong>{{ $item['product_name'] }}</strong></td>
                                <td class="text-center">{{ $item['unit'] }}</td>
                                <td class="text-right">&#8377;{{ number_format($item['rate'], 2) }}</td>
                                <td class="text-center" style="font-weight: bold;">{{ (int)$item['quantity'] == $item['quantity'] ? (int)$item['quantity'] : number_format($item['quantity'], 1) }}</td>
                                <td class="text-right" style="font-weight: bold;">&#8377;{{ number_format($item['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div style="text-align: center; padding: 24px; color: #94a3b8; border: 1px dashed #cbd5e1; border-radius: 4px;">
                No public sales recorded on {{ $details['formatted_date'] }} for this party.
            </div>
        @endforelse

        @if(!empty($details['entries']))
            <div class="summary-card">
                <div class="summary-title">DAILY TOTAL SUMMARY &bull; {{ $details['formatted_date'] }}</div>
                <table style="width: 100%; border: none; margin: 0;">
                    <tr>
                        <td style="border: none; padding: 0; vertical-align: top; width: 60%;">
                            <div style="font-size: 8pt; color: #065f46; font-weight: bold; margin-bottom: 4px;">Product Quantities Sold:</div>
                            @foreach($details['day_summary']['product_totals'] as $pName => $qty)
                                <span style="display: inline-block; background-color: #ffffff; border: 1px solid #a7f3d0; border-radius: 3px; padding: 2px 6px; margin: 2px 4px 2px 0; font-size: 7.5pt; color: #047857;">
                                    {{ $pName }}: <strong>{{ $qty }}</strong>
                                </span>
                            @endforeach
                        </td>
                        <td style="border: none; padding: 0; vertical-align: top; text-align: right; width: 40%;">
                            <div style="font-size: 8pt; color: #065f46;">Total Entries: <strong>{{ $details['day_summary']['total_entries'] }}</strong></div>
                            <div style="font-size: 8pt; color: #065f46; margin-top: 2px;">Total Cash: <strong>&#8377;{{ number_format($details['day_summary']['total_cash'], 2) }}</strong></div>
                            <div style="font-size: 8pt; color: #065f46; margin-top: 2px;">Total UPI: <strong>&#8377;{{ number_format($details['day_summary']['total_upi'], 2) }}</strong></div>
                            <div style="font-size: 11pt; color: #065f46; font-weight: bold; margin-top: 4px;">Daily Grand Total: &#8377;{{ number_format($details['day_summary']['daily_grand_total'], 2) }}</div>
                        </td>
                    </tr>
                </table>
            </div>
        @endif

        <div class="footer">
            This document is system-generated by {{ $details['company']['name'] }} &bull; Public Sales Module &bull; Powered by DharviCloud
        </div>
    </div>

    @if(!empty($autoPrint))
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 250);
        });
    </script>
    @endif
</body>
</html>
