<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; margin: 18px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #475569; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #e2e8f0; padding: 4px 5px; text-align: left; }
        th { background: #f1f5f9; font-size: 9px; text-transform: uppercase; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .summary { margin-top: 12px; }
        .summary td { border: none; padding: 2px 6px 2px 0; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        {{ __('Zhako BuildVault') }}
        · {{ strtoupper($locale ?? 'en') }}
        · {{ optional($generatedAt)->toDateTimeString() }}
        @if(!empty($report['filters']['from']))
            · {{ $report['filters']['from'] }} → {{ $report['filters']['to'] ?? '' }}
        @endif
        @if(!empty($report['filters']['month']))
            · {{ $report['filters']['month'] }}
        @endif
    </div>

    @if(($report['rows'] ?? []) === [])
        <p class="muted">{{ __('No data for this report.') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach($report['columns'] as $col)
                        <th class="{{ ($col['align'] ?? '') === 'end' ? 'num' : '' }}">{{ __($col['label_key']) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($report['rows'] as $row)
                    <tr>
                        @foreach($report['columns'] as $col)
                            @php $val = $row[$col['key']] ?? '—'; @endphp
                            <td class="{{ ($col['align'] ?? '') === 'end' ? 'num' : '' }}">
                                @if(!empty($col['money']) && is_numeric($val))
                                    {{ number_format((float) $val, 0) }}
                                @else
                                    {{ is_scalar($val) ? $val : '—' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($report['summary']))
        <table class="summary">
            @foreach($report['summary'] as $line)
                <tr>
                    <td><strong>{{ __($line['label_key']) }}</strong></td>
                    <td class="num">
                        @if(!empty($line['money']) && is_numeric($line['value']))
                            {{ number_format((float) $line['value'], 0) }} IQD
                        @else
                            {{ is_scalar($line['value']) ? $line['value'] : '' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
