@push('styles')
    <style>
        .archive-export-button {
            background-color: #16a34a !important; /* green-600 */
            color: #ffffff !important;
            border-color: #15803d !important;
        }

        .archive-export-button:hover,
        .archive-export-button:focus {
            background-color: #15803d !important; /* green-700 */
            color: #ffffff !important;
            border-color: #166534 !important;
        }

        .dark .archive-export-button {
            background-color: #22c55e !important; /* green-500 */
            color: #0b1120 !important;
            border-color: #16a34a !important;
        }

        .dark .archive-export-button:hover,
        .dark .archive-export-button:focus {
            background-color: #16a34a !important; /* green-600 */
            color: #0b1120 !important;
            border-color: #15803d !important;
        }
    </style>
@endpush

<div class="w-full overflow-x-auto">
    @php
        $archiveData = json_decode($record->json_data, true);
        $date = strtotime($record->date);
        $month = date('n', $date);
        $year = date('Y', $date);
        $monthDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        
        // Get non-working days (weekends)
        $weekends = [];
        for ($day = 1; $day <= $monthDays; $day++) {
            $dayOfWeek = date('N', strtotime("$year-$month-$day"));
            if ($dayOfWeek == 6 || $dayOfWeek == 7) { // Saturday or Sunday
                $weekends[] = $day;
            }
        }
        
        $monthNames = [
            1 => 'януари', 2 => 'февруари', 3 => 'март', 4 => 'април',
            5 => 'май', 6 => 'юни', 7 => 'юли', 8 => 'август',
            9 => 'септември', 10 => 'октомври', 11 => 'ноември', 12 => 'декември'
        ];
        
        $totalBudget = 0;
        $totalUsedBudget = 0;
        
        if ($archiveData && !empty($archiveData)) {
            $firstActivity = array_values($archiveData)[0];
            $totalBudget = $firstActivity['workPlaceBudget'] ?? 0;
            $totalUsedBudget = $firstActivity['workPlaceTotalUsedBudget'] ?? 0;
        }

        $monthOptions = $archiveMonthOptions ?? [];
        $selectedMonthLabel = $selectedMonthLabel ?? ($monthNames[$month] ?? date('m.Y', $date));
        $exportLink = $exportUrl ?? null;
    @endphp

    @if(!$archiveData || empty($archiveData))
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
            <div class="text-yellow-600 text-lg font-medium">⚠️ Няма данни за показване</div>
            <div class="text-yellow-700 text-sm mt-2">Архивираните данни не могат да бъдат декодирани или са празни.</div>
        </div>
    @else
        <!-- Archive Header -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">
                    Архив на присъствена форма за <strong>{{ $monthNames[$month] ?? $month }} {{ $year }}</strong> месец
                </h3>
                <div class="flex flex-wrap gap-3 items-center">
                    @if(!empty($monthOptions))
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <span class="font-medium">Месец:</span>
                            <select class="rounded-md border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500"
                                onchange="if (this.value) { window.location.href = this.value; }">
                                @foreach($monthOptions as $option)
                                    <option value="{{ $option['url'] }}" @selected($option['label'] === $selectedMonthLabel)>
                                        {{ $option['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <div class="text-sm text-gray-600">
                        <span>Бюджет на обекта: <strong>{{ number_format($totalUsedBudget, 0) }} / {{ number_format($totalBudget, 0) }} лв</strong></span>
                    </div>

                    @if($exportLink)
                        <a href="{{ $exportLink }}" target="_blank"
                           class="inline-flex items-center gap-2 rounded-md archive-export-button px-3 py-2 text-sm font-semibold shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">
                            <span aria-hidden="true">📄</span>
                            Експорт Excel
                        </a>
                    @endif
                </div>
            </div>
        </div>

        @foreach($archiveData as $activityId => $activity)
            @if(isset($activity['workPlaceActivityWorkers']) && !empty($activity['workPlaceActivityWorkers']))
                <!-- Activity Header -->
                <div class="bg-gray-50 dark:bg-gray-800 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-t-lg p-3 mt-6">
                    <div class="flex justify-between items-center">
                        <h4 class="font-semibold text-gray-800">
                            {{ $activity['workPlaceActivityName'] ?? 'Активност ' . $activityId }}
                        </h4>
                        <div class="text-sm text-gray-600">
                            Заплата: <strong>{{ number_format($activity['workPlaceActivitySalary'] ?? 0, 0) }} лв</strong> |
                            Макс. часове: <strong>{{ $activity['workPlaceActivityMaxWorkingHours'] ?? 0 }}</strong> |
                            Цена на час: <strong>{{ number_format($activity['workPlaceActivityHourPrice'] ?? 0, 2) }} лв</strong>
                        </div>
                    </div>
                </div>

                <!-- Presence Table -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-b-lg overflow-hidden">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-800 dark:bg-gray-700 text-red">
                                <th class="border border-gray-600 dark:border-gray-500 p-2 text-left min-w-[100px]">Длъжност</th>
                                <th class="border border-gray-600 dark:border-gray-500 p-2 text-left min-w-[80px]">Заплата</th>
                                <th class="border border-gray-600 dark:border-gray-500 p-2 text-left min-w-[100px]">Име</th>
                                <th class="border border-gray-600 dark:border-gray-500 p-2 text-left min-w-[100px]">Фамилия</th>
                                
                                @for($day = 1; $day <= $monthDays; $day++)
                                    <th class="border border-gray-600 dark:border-gray-500 p-1 text-center min-w-[30px] 
                                        {{ in_array($day, $weekends) ? 'bg-gray-300 text-gray-600' : '' }}">
                                        {{ $day }}
                                    </th>
                                @endfor
                                
                                <th class="border border-gray-600 p-2 text-center min-w-[80px]">Цена</th>
                                <th class="border border-gray-600 p-2 text-center min-w-[80px]">Общо</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $activityTotal = 0;
                                $activityHours = 0;
                            @endphp
                            
                            @foreach($activity['workPlaceActivityWorkers'] as $worker)
                                @php
                                    $workerRecords = [];
                                    if (isset($worker['worker_records'])) {
                                        foreach ($worker['worker_records'] as $record) {
                                            if (isset($record['date'])) {
                                                $recordDay = date('j', strtotime($record['date']));
                                                $workerRecords[$recordDay] = $record['hours'] ?? 0;
                                            }
                                        }
                                    }
                                    
                                    $workerTotal = array_sum($workerRecords);
                                    $workerPrice = $workerTotal * ($activity['workPlaceActivityHourPrice'] ?? 0);
                                    $activityTotal += $workerPrice;
                                    $activityHours += $workerTotal;
                                @endphp
                                
                                <tr class="border-b border-gray-100 hover:bg-gray-50 dark:bg-gray-800">
                                    <td class="border border-gray-200 dark:border-gray-600 p-2 font-medium bg-gray-50 dark:bg-gray-800">
                                        {{ $activity['workPlaceActivityName'] ?? '' }}
                                    </td>
                                    <td class="border border-gray-200 dark:border-gray-600 p-2 text-right font-medium">
                                        {{ number_format($worker['neto_salary'] ?? 0, 0) }}
                                    </td>
                                    <td class="border border-gray-200 dark:border-gray-600 p-2">{{ $worker['name'] ?? '' }}</td>
                                    <td class="border border-gray-200 dark:border-gray-600 p-2">{{ $worker['family_name'] ?? '' }}</td>
                                    
                                    @for($day = 1; $day <= $monthDays; $day++)
                                        @php
                                            $hours = $workerRecords[$day] ?? 0;
                                            $isWeekend = in_array($day, $weekends);
                                        @endphp
                                        
                                        <td class="border border-gray-200 dark:border-gray-600 p-1 text-center text-xs
                                            @if($hours > 0 && !$isWeekend)
                                                bg-red-500 text-dark font-bold
                                            @elseif($hours > 0 && $isWeekend)
                                                bg-blue-500 text-dark font-bold
                                            @elseif($isWeekend)
                                                bg-gray-100 dark:bg-gray-700
                                            @endif
                                        ">
                                            @if($hours > 0)
                                                {{ $hours }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    @endfor
                                    
                                    <td class="border border-gray-200 dark:border-gray-600 p-2 text-right font-medium">
                                        {{ number_format($workerPrice, 0) }}
                                    </td>
                                    <td class="border border-gray-200 dark:border-gray-600 p-2 text-right font-bold">
                                        {{ $workerTotal }}
                                    </td>
                                </tr>
                            @endforeach
                            
                            <!-- Activity Total Row -->
                            <tr class="bg-yellow-100 font-bold">
                                <td class="border border-gray-400 p-2" colspan="4">
                                    Общо за {{ $activity['workPlaceActivityName'] ?? 'активността' }}:
                                </td>
                                @for($day = 1; $day <= $monthDays; $day++)
                                    @php
                                        $dayTotal = 0;
                                        foreach($activity['workPlaceActivityWorkers'] as $worker) {
                                            if (isset($worker['worker_records'])) {
                                                foreach ($worker['worker_records'] as $record) {
                                                    if (isset($record['date']) && date('j', strtotime($record['date'])) == $day) {
                                                        $dayTotal += $record['hours'] ?? 0;
                                                    }
                                                }
                                            }
                                        }
                                    @endphp
                                    <td class="border border-gray-400 p-1 text-center text-xs
                                        {{ $dayTotal > 0 ? 'bg-orange-200 font-bold' : '' }}">
                                        {{ $dayTotal > 0 ? $dayTotal : '-' }}
                                    </td>
                                @endfor
                                <td class="border border-gray-400 p-2 text-right">{{ number_format($activityTotal, 0) }}</td>
                                <td class="border border-gray-400 p-2 text-right">{{ $activityHours }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach

        @if($exportLink)
            <div class="flex justify-end mt-6">
                <a href="{{ $exportLink }}" target="_blank"
                   class="inline-flex items-center gap-2 rounded-md archive-export-button px-4 py-2 text-sm font-semibold shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">
                    <span aria-hidden="true">📄</span>
                    Експорт Excel
                </a>
            </div>
        @endif
    @endif
</div>

<style>
    /* Additional styling for better table appearance */
    .presence-table th,
    .presence-table td {
        white-space: nowrap;
    }
    
    .presence-table .day-cell {
        width: 30px;
        max-width: 30px;
        text-align: center;
    }
</style>
