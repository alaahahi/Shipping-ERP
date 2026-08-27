<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sorted Inventory — Land Transit</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 0 0 8px; font-weight: normal; }
        .meta { color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
        th { background: #f3f3f3; font-weight: bold; }
        td.num, th.num { text-align: right; }
        td.vin { font-family: DejaVu Sans Mono, DejaVu Sans, sans-serif; font-size: 9px; }
        tfoot td { font-weight: bold; background: #fafafa; }
    </style>
</head>
<body>
    <h1>Land Transit</h1>
    <h2>Sorted Inventory</h2>
    <div class="meta">
        Generated: {{ $generated_at }}
        · Cars: {{ $count }}
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Company</th>
                <th>Vehicle Model</th>
                <th>Color</th>
                <th>Year</th>
                <th>CMR</th>
                <th>VIN</th>
                <th>Status</th>
                <th>Consignee</th>
                <th class="num">Price</th>
                <th>Weight</th>
                <th>Notes</th>
                <th>Entered At</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="num">{{ $row['serial'] }}</td>
                    <td>{{ $row['company'] !== '' ? $row['company'] : '—' }}</td>
                    <td>{{ $row['model'] !== '' ? $row['model'] : '—' }}</td>
                    <td>{{ $row['color'] !== '' ? $row['color'] : '—' }}</td>
                    <td>{{ $row['year'] !== '' ? $row['year'] : '—' }}</td>
                    <td>{{ $row['cmr'] !== '' ? $row['cmr'] : '—' }}</td>
                    <td class="vin">{{ $row['vin'] !== '' ? $row['vin'] : '—' }}</td>
                    <td>{{ $row['status'] !== '' ? $row['status'] : '—' }}</td>
                    <td>{{ $row['consignee'] !== '' ? $row['consignee'] : '—' }}</td>
                    <td class="num">{{ $row['price'] }}</td>
                    <td>{{ $row['weight'] !== '' ? $row['weight'] : '—' }}</td>
                    <td>{{ $row['notes'] !== '' ? $row['notes'] : '—' }}</td>
                    <td>{{ $row['entered_at'] !== '' ? $row['entered_at'] : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13">No cars match the selected filter.</td>
                </tr>
            @endforelse
        </tbody>
        @if (count($rows) > 0)
            <tfoot>
                <tr>
                    <td colspan="9">Total</td>
                    <td class="num">{{ $total_price }}</td>
                    <td colspan="3">{{ $count }} cars</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
