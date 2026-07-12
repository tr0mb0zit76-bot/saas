<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Счёт {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 32px; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .totals { margin-top: 16px; text-align: right; font-size: 14px; }
    </style>
</head>
<body>
    <h1>Счёт на оплату {{ $invoice->invoice_number }}</h1>
    <p class="muted">Traklo Pro · {{ now()->format('d.m.Y') }}</p>

    <p><strong>Арендатор:</strong> {{ $tenant->name }} ({{ $tenant->slug }})</p>
    <p><strong>Тариф:</strong> {{ $planLabel }}</p>
    <p><strong>Период:</strong>
        {{ $invoice->period_start?->format('d.m.Y') }} — {{ $invoice->period_end?->format('d.m.Y') }}
    </p>
    <p><strong>Статус:</strong> {{ $invoice->status === 'paid' ? 'Оплачен' : 'Выставлен' }}</p>

    <table>
        <thead>
            <tr>
                <th>Наименование</th>
                <th>Период</th>
                <th>Сумма</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Подписка Traklo Pro, тариф {{ $planLabel }}</td>
                <td>{{ $invoice->period_start?->format('d.m.Y') }} — {{ $invoice->period_end?->format('d.m.Y') }}</td>
                <td>{{ number_format((float) $invoice->amount, 2, ',', ' ') }} {{ $invoice->currency }}</td>
            </tr>
        </tbody>
    </table>

    <p class="totals"><strong>Итого:</strong> {{ number_format((float) $invoice->amount, 2, ',', ' ') }} {{ $invoice->currency }}</p>

    @if ($invoice->notes)
        <p><strong>Примечание:</strong> {{ $invoice->notes }}</p>
    @endif

    <p class="muted" style="margin-top: 32px;">Документ сформирован автоматически. УПД выставляется отдельно по запросу (ADR-009).</p>
</body>
</html>
