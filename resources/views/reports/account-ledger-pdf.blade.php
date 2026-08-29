<!DOCTYPE html>
<html lang="{{ $locale }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>{{ $payload['account']['code'] }} — {{ $labels['ledger'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; direction: ltr; }
        h1 { font-size: 16px; margin: 0 0 2px; {{ $rtl ? 'text-align: right;' : '' }} }
        h2 { font-size: 13px; margin: 0 0 8px; font-weight: normal; {{ $rtl ? 'text-align: right;' : '' }} }
        .meta { color: #555; margin-bottom: 10px; {{ $rtl ? 'text-align: right;' : '' }} }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .summary td { border: 1px solid #ccc; padding: 5px 6px; }
        .summary .lbl { background: #f3f3f3; font-weight: bold; width: 18%; {{ $rtl ? 'text-align: right;' : '' }} }
        table.lines { width: 100%; border-collapse: collapse; }
        .lines th, .lines td { border: 1px solid #ccc; padding: 4px 6px; text-align: {{ $rtl ? 'right' : 'left' }}; }
        .lines th { background: #f3f3f3; font-weight: bold; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: bold; background: #fafafa; }
        .lines tr.note td { background: #ccfbf1; }
    </style>
</head>
<body>
    <h1>{{ $company }}</h1>
    <h2>{{ $labels['ledger'] }} — {{ $payload['account']['code'] }} {{ $payload['account']['name'] }}</h2>
    <div class="meta">
        {{ $payload['account']['type_label'] }} · {{ $payload['account']['currency'] }}
        · {{ $period }}
        · {{ $labels['generated'] }}: {{ $generated_at }}
    </div>

    <table class="summary">
        <tr>
            <td class="lbl">{{ $labels['opening'] }}</td>
            <td class="num">{{ $payload['opening_balance'] }}</td>
            <td class="lbl">{{ $labels['debit'] }}</td>
            <td class="num">{{ $payload['period_debit'] }}</td>
            <td class="lbl">{{ $labels['credit'] }}</td>
            <td class="num">{{ $payload['period_credit'] }}</td>
        </tr>
        <tr>
            <td class="lbl">{{ $labels['period_net'] }}</td>
            <td class="num">{{ $payload['period_net'] }}</td>
            <td class="lbl">{{ $labels['closing'] }}</td>
            <td class="num">{{ $payload['closing_balance'] }}</td>
            <td class="lbl"></td>
            <td></td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ $labels['date'] }}</th>
                <th>{{ $labels['voucher'] }}</th>
                <th>{{ $labels['description'] }}</th>
                <th>{{ $labels['source'] }}</th>
                <th class="num">{{ $labels['debit'] }}</th>
                <th class="num">{{ $labels['credit'] }}</th>
                <th class="num">{{ $labels['balance'] }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payload['lines'] as $row)
                @php $isNote = ($row['row_type'] ?? '') === 'note'; @endphp
                <tr class="{{ $isNote ? 'note' : '' }}">
                    <td>{{ $row['entry_date'] ?? '—' }}</td>
                    <td>{{ $isNote ? $labels['note'] : ($row['voucher_number'] ?? '—') }}</td>
                    <td>
                        {{ $row['description'] ?? '' }}
                        @if (! $isNote && ! empty($row['memo']))
                            / {{ $row['memo'] }}
                        @endif
                    </td>
                    <td>{{ $isNote ? '—' : ($row['counterpart']['label'] ?? '—') }}</td>
                    <td class="num">{{ $isNote ? '' : $row['debit'] }}</td>
                    <td class="num">{{ $isNote ? '' : $row['credit'] }}</td>
                    <td class="num">{{ $row['balance'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">{{ $labels['empty'] }}</td>
                </tr>
            @endforelse
        </tbody>
        @if (count($payload['lines']) > 0)
            <tfoot>
                <tr>
                    <td colspan="4">{{ $labels['period_net'] }}</td>
                    <td class="num">{{ $payload['period_debit'] }}</td>
                    <td class="num">{{ $payload['period_credit'] }}</td>
                    <td class="num">{{ $payload['closing_balance'] }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
