<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Payment voucher') }} #{{ $payout->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #0f172a; margin: 20px; }
        .brand { font-size: 20px; font-weight: bold; letter-spacing: 0.04em; }
        .sub { color: #64748b; margin-bottom: 14px; }
        h1 { font-size: 16px; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px 8px; vertical-align: top; }
        .box { border: 1px solid #cbd5e1; border-radius: 2px; }
        .label { color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; }
        .amount { font-size: 22px; font-weight: bold; }
        .dual { font-size: 14px; margin-top: 4px; }
        .footer { margin-top: 18px; font-size: 10px; color: #64748b; }
        .sig { margin-top: 28px; width: 100%; }
        .sig td { border-top: 1px solid #94a3b8; width: 40%; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="brand">ZHAKO</div>
    <div class="sub">{{ __('Zhako BuildVault') }} · {{ __('Payment voucher') }}</div>

    <h1>{{ __('Voucher') }} #{{ $payout->id }}</h1>

    <table class="box">
        <tr>
            <td width="50%">
                <div class="label">{{ __('Project') }}</div>
                <div>{{ $payout->project?->name ?? '—' }}</div>
                <div class="sub">{{ $payout->project?->location }}</div>
            </td>
            <td width="50%">
                <div class="label">{{ __('Worker') }}</div>
                <div>{{ $payout->worker?->name ?? '—' }}</div>
                <div class="sub">{{ $payout->worker?->role }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">{{ __('Category') }}</div>
                <div>{{ $payout->category }}</div>
            </td>
            <td>
                <div class="label">{{ __('Status') }}</div>
                <div>{{ $payout->status }}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <div class="label">{{ __('Amount') }}</div>
                <div class="amount">${{ number_format((float) $payout->amount_usd, 2) }} USD</div>
                <div class="dual">
                    {{ format_iqd($payout->amount_iqd) }}
                    · {{ __('Rate') }} {{ format_number($payout->exchange_rate, 2) }}
                </div>
                @if((float) $payout->retention_holdback > 0)
                    <div class="sub" style="margin-top:6px">
                        {{ __('Retention holdback') }}: ${{ number_format((float) $payout->retention_holdback, 2) }} USD
                    </div>
                @endif
            </td>
        </tr>
        @if($payout->notes)
            <tr>
                <td colspan="2">
                    <div class="label">{{ __('Notes') }}</div>
                    <div>{{ $payout->notes }}</div>
                </td>
            </tr>
        @endif
    </table>

    <table class="sig">
        <tr>
            <td>{{ __('Prepared by') }}: {{ $payout->creator?->name ?? '—' }}</td>
            <td style="width:10%"></td>
            <td>{{ __('Received by') }}</td>
        </tr>
    </table>

    <div class="footer">
        {{ __('Generated') }}: {{ $generatedAt->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
        · {{ strtoupper($locale ?? 'en') }}
        · {{ $payout->vault?->name }}
    </div>
</body>
</html>
