<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Присъствена форма - {{ $workplaceData->name }} - {{ sprintf('%02d-%d', $month, $year) }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #111827;
            background: #ffffff;
        }

        .toolbar {
            display: flex;
            gap: 8px;
            margin: 12px;
        }

        .toolbar button,
        .toolbar a {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #111827;
            padding: 6px 10px;
            font-size: 12px;
            text-decoration: none;
            cursor: pointer;
        }

        .page {
            padding: 0 12px 12px;
        }

        h1 {
            margin: 0 0 6px;
            font-size: 16px;
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 8px;
            font-size: 11px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9px;
        }

        th,
        td {
            border: 1px solid #6b7280;
            padding: 2px 3px;
            vertical-align: middle;
        }

        thead th {
            background: #111827;
            color: #ffffff;
            text-align: center;
        }

        .activity-row td {
            background: #dcfce7;
            font-weight: 700;
        }

        .col-activity {
            width: 90px;
            text-align: left;
        }

        .col-salary {
            width: 70px;
            text-align: right;
        }

        .col-name {
            width: 90px;
            text-align: left;
        }

        .col-day {
            width: 26px;
            text-align: center;
        }

        .col-total {
            width: 84px;
            text-align: right;
            white-space: nowrap;
        }

        .non-working {
            background: #e5e7eb;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        @media print {
            .toolbar {
                display: none;
            }

            .page {
                padding: 0;
            }
        }
    </style>
</head>
<body>
@php
    $formatPresenceNumber = function ($value, int $decimals = 2): string {
        $formatted = number_format((float) $value, $decimals, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    };
@endphp

<div class="toolbar">
    <button type="button" onclick="window.print()">Печат / Запази като PDF</button>
    <a href="{{ url()->previous() }}">Назад</a>
</div>

<div class="page">
    <h1>Присъствена форма</h1>
    <div style="margin-bottom: 6px; font-size: 14px; font-weight: 700;">
        {{ $workplaceData->name }}
    </div>
    <div class="meta">
        <div><strong>Обект:</strong> {{ $workplaceData->name }}</div>
        <div><strong>Клиент:</strong> {{ $workplaceData->client?->name ?? '-' }}</div>
        <div><strong>Регион:</strong> {{ $workplaceData->region?->name ?? '-' }}</div>
        <div><strong>Месец:</strong> {{ $monthName }}</div>
        <div><strong>Генерирано:</strong> {{ now()->format('d.m.Y H:i') }}</div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
            <tr>
                <th class="col-activity">Длъжност</th>
                <th class="col-salary">Заплата</th>
                <th class="col-name">Име</th>
                <th class="col-name">Фамилия</th>
                <th class="col-name">Презиме</th>
                @for ($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $specialDay = $specialDayMap[$day] ?? null;
                        $headerTitle = $specialDay['label'] ?? '';
                    @endphp
                    <th class="col-day {{ isset($nonWorkingDaysMap[$day]) ? 'non-working' : '' }}" title="{{ $headerTitle }}">{{ $day }}</th>
                @endfor
                <th class="col-total">Цена</th>
                <th class="col-total">Общо</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($groupedByActivity as $activity)
                <tr class="activity-row">
                    <td class="col-activity">{{ $activity['activity_name'] }}</td>
                    <td class="col-salary">{{ $formatPresenceNumber($activity['activity_salary']) }}</td>
                    <td colspan="3" class="center">-</td>
                    @for ($day = 1; $day <= $daysInMonth; $day++)
                        <td class="col-day {{ isset($nonWorkingDaysMap[$day]) ? 'non-working' : '' }}">-</td>
                    @endfor
                    <td class="col-total">
                        {{ $formatPresenceNumber($activity['group_totals']['used_budget'] ?? 0) }}
                        / {{ $formatPresenceNumber($activity['group_totals']['max_budget'] ?? 0) }}
                    </td>
                    <td class="col-total">
                        {{ $formatPresenceNumber($activity['group_totals']['used_hours'] ?? 0) }}
                        / {{ $formatPresenceNumber($activity['group_totals']['max_hours'] ?? 0) }}
                    </td>
                </tr>

                @foreach ($activity['workers'] as $workerData)
                    <tr>
                        <td class="col-activity">-</td>
                        <td class="col-salary">-</td>
                        <td class="col-name">{{ $workerData['worker']->name }}</td>
                        <td class="col-name">{{ $workerData['worker']->family_name }}</td>
                        <td class="col-name">{{ $workerData['worker']->middle_name }}</td>
                        @for ($day = 1; $day <= $daysInMonth; $day++)
                            @php
                                $dayValue = (float) ($workerData['daily_records'][$day] ?? 0);
                            @endphp
                            <td class="col-day {{ isset($nonWorkingDaysMap[$day]) ? 'non-working' : '' }}">
                                {{ $dayValue > 0 ? $formatPresenceNumber($dayValue) : '-' }}
                            </td>
                        @endfor
                        <td class="col-total">{{ $formatPresenceNumber($workerData['calculated_price'] ?? 0) }}</td>
                        <td class="col-total">{{ $formatPresenceNumber($workerData['total_hours'] ?? 0) }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="{{ 7 + $daysInMonth }}" class="center">Няма данни за избрания период.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
