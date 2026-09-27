<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Worker profile') }} — {{ $worker->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 8px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        .meta { color: #475569; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #e2e8f0; padding: 4px 6px; text-align: left; }
        th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; }
        .grid { width: 100%; }
        .grid td { border: none; padding: 2px 8px 2px 0; vertical-align: top; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <h1>{{ __('Worker profile') }}</h1>
    <div class="meta">
        {{ __('Zhako BuildVault') }} · {{ $from }} → {{ $to }} · {{ strtoupper($locale ?? 'en') }}
    </div>

    <table class="grid">
        <tr>
            <td><strong>{{ __('Name') }}:</strong> {{ $worker->name }}</td>
            <td><strong>{{ __('Role') }}:</strong> {{ $worker->role }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('Project') }}:</strong> {{ $worker->project?->name ?? '—' }}</td>
            <td><strong>{{ __('Phone') }}:</strong> {{ $worker->phone ?: '—' }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('National ID') }}:</strong> {{ $worker->national_id_number ?: '—' }}</td>
            <td><strong>{{ __('Daily rate (USD)') }}:</strong> {{ number_format((float) $worker->daily_rate_usd, 2) }}</td>
        </tr>
    </table>

    <h2>{{ __('Document references') }}</h2>
    @if($documents->isEmpty())
        <p class="muted">{{ __('No profile photo or national ID documents on file.') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('File') }}</th>
                    <th>{{ __('Path') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($documents as $doc)
                    <tr>
                        <td>{{ $doc->type }}</td>
                        <td>{{ $doc->title ?: '—' }}</td>
                        <td>{{ $doc->original_name }}</td>
                        <td>{{ $doc->path }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>{{ __('Payroll summary') }}</h2>
    <table>
        <tr><th>{{ __('Days present') }}</th><td class="num">{{ $payroll['days_present'] }}</td></tr>
        <tr><th>{{ __('Overtime hours') }}</th><td class="num">{{ number_format($payroll['overtime_hours'], 2) }}</td></tr>
        <tr><th>{{ __('Base pay (USD)') }}</th><td class="num">{{ number_format($payroll['base_pay_usd'], 2) }}</td></tr>
        <tr><th>{{ __('Overtime pay (USD)') }}</th><td class="num">{{ number_format($payroll['overtime_pay_usd'], 2) }}</td></tr>
        <tr><th>{{ __('Late penalty (USD)') }}</th><td class="num">{{ number_format($payroll['late_penalty_usd'], 2) }}</td></tr>
        <tr><th>{{ __('Absence penalty (USD)') }}</th><td class="num">{{ number_format($payroll['absence_penalty_usd'], 2) }}</td></tr>
        <tr><th>{{ __('Net pay (USD)') }}</th><td class="num"><strong>{{ number_format($payroll['net_pay_usd'], 2) }}</strong></td></tr>
    </table>

    <h2>{{ __('Attendance') }} ({{ $attendances->count() }})</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Check in') }}</th>
                <th>{{ __('Check out') }}</th>
                <th>{{ __('Late') }}</th>
                <th>{{ __('OT') }}</th>
                <th>{{ __('Floor') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendances as $row)
                <tr>
                    <td>{{ optional($row->date)->toDateString() }}</td>
                    <td>{{ $row->status }}</td>
                    <td>{{ $row->check_in ?: '—' }}</td>
                    <td>{{ $row->check_out ?: '—' }}</td>
                    <td class="num">{{ $row->late_minutes }}</td>
                    <td class="num">{{ $row->overtime_hours }}</td>
                    <td>{{ $row->floor?->name ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">{{ __('No attendance in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>{{ __('Payouts in period') }}</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('Category') }}</th>
                <th>{{ __('USD') }}</th>
                <th>{{ __('IQD') }}</th>
                <th>{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payouts as $p)
                <tr>
                    <td>{{ $p->id }}</td>
                    <td>{{ $p->category }}</td>
                    <td class="num">{{ number_format((float) $p->amount_usd, 2) }}</td>
                    <td class="num">{{ number_format((float) $p->amount_iqd, 0) }}</td>
                    <td>{{ $p->status }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">{{ __('No payouts in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
