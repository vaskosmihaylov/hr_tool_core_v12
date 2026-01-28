@php
    use Carbon\Carbon;

    $month = $date->month;
    $year = $date->year;
    $monthDays = $date->daysInMonth;
    $monthNames = [
        1 => 'Януари', 2 => 'Февруари', 3 => 'Март', 4 => 'Април',
        5 => 'Май', 6 => 'Юни', 7 => 'Юли', 8 => 'Август',
        9 => 'Септември', 10 => 'Октомври', 11 => 'Ноември', 12 => 'Декември'
    ];

    $weekends = [];
    for ($day = 1; $day <= $monthDays; $day++) {
        $dayOfWeek = Carbon::create($year, $month, $day)->dayOfWeekIso;
        if ($dayOfWeek >= 6) {
            $weekends[] = $day;
        }
    }

    $totalBudget = 0;
    $totalUsedBudget = 0;
    if (!empty($archiveData)) {
        $firstActivity = array_values($archiveData)[0];
        $totalBudget = $firstActivity['workPlaceBudget'] ?? 0;
        $totalUsedBudget = $firstActivity['workPlaceTotalUsedBudget'] ?? 0;
    }
@endphp

<table>
    <tr>
        <th colspan="{{ 6 + $monthDays }}">
            Присъствена форма - {{ $archive->workplace?->name }} ({{ $monthNames[$month] ?? $month }} {{ $year }})
        </th>
    </tr>
    <tr>
        <td colspan="{{ 6 + $monthDays }}">
            Бюджет: {{ number_format($totalUsedBudget, 2, ',', ' ') }} / {{ number_format($totalBudget, 2, ',', ' ') }} €
        </td>
    </tr>
</table>

@if(empty($archiveData))
    <table>
        <tr>
            <td>Няма данни за избрания архив.</td>
        </tr>
    </table>
@else
    @foreach($archiveData as $activity)
        @php
            $workers = $activity['workPlaceActivityWorkers'] ?? [];
            $activityName = $activity['workPlaceActivityName'] ?? 'Активност';
            $activityTotal = 0;
            $activityHours = 0;
        @endphp

        <table>
            <tr>
                <th colspan="{{ 6 + $monthDays }}">{{ $activityName }}</th>
            </tr>
            <tr>
                <th>Длъжност</th>
                <th>Заплата</th>
                <th>Име</th>
                <th>Фамилия</th>
                @for($day = 1; $day <= $monthDays; $day++)
                    <th>{{ $day }}</th>
                @endfor
                <th>Цена</th>
                <th>Общо часове</th>
            </tr>

            @foreach($workers as $worker)
                @php
                    $workerRecords = $worker['worker_records'] ?? [];
                    $workerTotal = 0;
                    $workerPrice = $worker['workPlaceActivityHourPrice'] ?? 0;
                    $workerSumPrice = 0;

                    $recordsByDay = [];
                    foreach ($workerRecords as $record) {
                        if (!isset($record['date'])) {
                            continue;
                        }
                        $dayIndex = (int) Carbon::parse($record['date'])->format('j');
                        $recordsByDay[$dayIndex] = $record['hours'] ?? 0;
                    }
                @endphp

                <tr>
                    <td>{{ $worker['worker_position'] ?? '' }}</td>
                    <td>{{ number_format($worker['workPlaceActivitySalary'] ?? 0, 2, ',', ' ') }}</td>
                    <td>{{ $worker['worker_name'] ?? '' }}</td>
                    <td>{{ $worker['worker_family'] ?? '' }}</td>

                    @for($day = 1; $day <= $monthDays; $day++)
                        @php
                            $hours = $recordsByDay[$day] ?? 0;
                            $workerTotal += $hours;
                        @endphp
                        <td>{{ $hours > 0 ? $hours : '' }}</td>
                    @endfor

                    @php
                        $workerSumPrice = $workerTotal * ($worker['workPlaceActivityHourPrice'] ?? 0);
                        $activityTotal += $workerSumPrice;
                        $activityHours += $workerTotal;
                    @endphp

                    <td>{{ number_format($workerSumPrice, 2, ',', ' ') }}</td>
                    <td>{{ $workerTotal }}</td>
                </tr>
            @endforeach

            <tr>
                <td colspan="4"><strong>Общо за {{ $activityName }}</strong></td>
                @for($day = 1; $day <= $monthDays; $day++)
                    @php
                        $dayTotal = 0;
                        foreach ($workers as $worker) {
                            $workerRecords = $worker['worker_records'] ?? [];
                            foreach ($workerRecords as $record) {
                                if (!isset($record['date'])) {
                                    continue;
                                }
                                if ((int) Carbon::parse($record['date'])->format('j') === $day) {
                                    $dayTotal += $record['hours'] ?? 0;
                                }
                            }
                        }
                    @endphp
                    <td>{{ $dayTotal > 0 ? $dayTotal : '' }}</td>
                @endfor
                <td>{{ number_format($activityTotal, 2, ',', ' ') }}</td>
                <td>{{ $activityHours }}</td>
            </tr>
        </table>
        <br>
    @endforeach
@endif
