<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Voyage Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        th { background: #f3f3f3; font-weight: bold; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: bold; background: #fafafa; }
    </style>
</head>
<body>
    <h1>Voyage Report</h1>
    <div class="meta">
        Generated: {{ $generated_at }}
        @if (!empty($filters['date_from']) || !empty($filters['date_to']))
            · Period: {{ $filters['date_from'] ?: '…' }} → {{ $filters['date_to'] ?: '…' }}
        @endif
        @if (!empty($filters['status']))
            · Status: {{ $filters['status'] }}
        @endif
        · Rows: {{ count($rows) }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Voyage</th>
                <th>Ship</th>
                <th>Sailing</th>
                <th>Status</th>
                <th>Route</th>
                <th class="num">Cars</th>
                <th class="num">Revenue USD</th>
                <th class="num">Expenses USD</th>
                <th class="num">Profit USD</th>
                <th class="num">Commission AED</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['voyage_number'] }}</td>
                    <td>{{ $row['ship_name'] ?? '—' }}</td>
                    <td>{{ $row['sailing_date'] ?? '—' }}</td>
                    <td>{{ $row['status_label'] ?? $row['status'] }}</td>
                    <td>{{ $row['route'] ?? '—' }}</td>
                    <td class="num">{{ $row['cars_count'] }}</td>
                    <td class="num">{{ $row['revenue_usd'] }}</td>
                    <td class="num">{{ $row['expenses_usd'] }}</td>
                    <td class="num">{{ $row['profit_usd'] }}</td>
                    <td class="num">{{ $row['commission_aed'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No voyages match the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
        @if (count($rows) > 0)
            <tfoot>
                <tr>
                    <td colspan="5">Totals</td>
                    <td class="num">{{ $totals['cars'] }}</td>
                    <td class="num">{{ $totals['revenue_usd'] }}</td>
                    <td class="num">{{ $totals['expenses_usd'] }}</td>
                    <td class="num">{{ $totals['profit_usd'] }}</td>
                    <td class="num">{{ $totals['commission_aed'] }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
