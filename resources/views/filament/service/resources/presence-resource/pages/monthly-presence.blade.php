<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Section --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $workplaces[$workplace] ?? 'Неизвестно работно място' }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Месечно управление на присъствието за {{ $this->getMonthName() }}
                    </p>
                    @if($isLocked)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 mt-2">
                            <x-heroicon-s-lock-closed class="w-3 h-3 mr-1" />
                            Месецът е заключен
                        </span>
                    @endif
                </div>
                
                <div class="text-center">
                    <div class="text-lg font-semibold text-indigo-600 dark:text-indigo-400 mb-2">
                        {{ $this->getMonthName() }}
                    </div>
                    @if($hasUnsavedChanges)
                        <div class="text-sm text-orange-600 dark:text-orange-400">⚠️ Незапазени промени</div>
                    @endif
                </div>

                <div class="text-right">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Навигацията се управлява от бутоните в горния ред
                    </div>
                </div>
            </div>
        </div>

        {{-- Monthly Statistics --}}
        @if($monthlyData && $monthlyData->count() > 0)
            @php
                $totalWorkers = $monthlyData->sum(function($group) { return count($group['workers']); });
                $totalHours = $monthlyData->sum(function($group) { 
                    $hours = 0;
                    foreach($group['workers'] as $worker) {
                        $hours += $worker['total_hours'];
                    }
                    return $hours;
                });
                $averageHours = $totalWorkers > 0 ? $totalHours / $totalWorkers : 0;
            @endphp
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @foreach([
                    ['label' => 'Общо работници', 'value' => $totalWorkers, 'color' => 'gray'],
                    ['label' => 'Общо часове', 'value' => number_format($totalHours, 1), 'color' => 'green'],
                    ['label' => 'Средно часове/ден', 'value' => number_format($averageHours, 1), 'color' => 'blue'],
                    ['label' => 'Дни в месеца', 'value' => $this->getDaysInMonth(), 'color' => 'indigo']
                ] as $stat)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</div>
                        <div class="text-2xl font-bold text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-400">{{ $stat['value'] }}</div>
                    </div>
                @endforeach
            </div>
            {{-- Monthly Table --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Редактируема таблица за {{ $this->getMonthName() }}
                        </h3>
                        <div class="flex space-x-2">

                            @if(!$isLocked)
                                <button wire:click="saveHours" 
                                        @disabled(!$hasUnsavedChanges)
                                        class="inline-flex items-center px-3 py-2 border-0 text-sm font-medium rounded-md text-white 
                                               {{ $hasUnsavedChanges ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed' }}">
                                    <x-heroicon-o-check class="w-4 h-4 mr-1" />
                                    Запази часовете
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[120px]">
                                    Длъжност
                                </th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[80px]">
                                    Заплата
                                </th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[100px]">
                                    Име
                                </th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[100px]">
                                    Фамилия
                                </th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[100px]">
                                    ЕГН
                                </th>
                                @for($day = 1; $day <= $this->getDaysInMonth(); $day++)
                                    @php
                                        $date = \Carbon\Carbon::create($year, $month, $day);
                                        $isWeekend = $date->isWeekend();
                                    @endphp
                                    <th class="px-1 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[50px] {{ $isWeekend ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                                        <div>{{ $day }}</div>
                                        <div class="text-xs font-normal">{{ $date->format('D') }}</div>
                                    </th>
                                @endfor
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[80px]">
                                    Цена
                                </th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[80px]">
                                    Общо
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($monthlyData as $activityGroup)
                                {{-- Activity Group Header Row --}}
                                <tr class="bg-gray-100 dark:bg-gray-700 font-semibold border-b-2 border-gray-300 dark:border-gray-600">
                                    <td class="px-3 py-4 whitespace-nowrap text-center">
                                        <div class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                            {{ $activityGroup['activity_name'] }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-center">
                                        <div class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                            {{ number_format($activityGroup['activity_salary'], 0) }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-center">
                                        <div class="text-sm font-bold text-gray-500 dark:text-gray-400">-</div>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-center">
                                        <div class="text-sm font-bold text-gray-500 dark:text-gray-400">-</div>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-center">
                                        <div class="text-sm font-bold text-gray-500 dark:text-gray-400">-</div>
                                    </td>
                                    @for($day = 1; $day <= $this->getDaysInMonth(); $day++)
                                        @php
                                            $date = \Carbon\Carbon::create($year, $month, $day);
                                            $isWeekend = $date->isWeekend();
                                        @endphp
                                        <td class="px-1 py-2 whitespace-nowrap text-center {{ $isWeekend ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                                            <div class="text-xs font-bold text-gray-500 dark:text-gray-400">-</div>
                                        </td>
                                    @endfor
                                    <td class="px-3 py-4 whitespace-nowrap text-center">
                                        <span class="text-sm font-bold text-blue-600 dark:text-blue-400">
                                            {{ number_format($activityGroup['group_totals']['total_price'], 2) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-center">
                                        <span class="text-sm font-bold text-green-600 dark:text-green-400">
                                            {{ number_format($activityGroup['group_totals']['total_calculated'], 2) }}
                                        </span>
                                    </td>
                                </tr>
                                
                                {{-- Individual Workers in this Activity --}}
                                @foreach($activityGroup['workers'] as $data)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-3 py-4 whitespace-nowrap text-center">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">-</div>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-center">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">-</div>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $data['worker']->name }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $data['worker']->family_name }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $data['worker']->egn }}</div>
                                            @if(!$isLocked)
                                                <button wire:click="removeWorkerFromMonth({{ $data['worker']->id }})" 
                                                        wire:confirm="Сигурни ли сте, че искате да премахнете {{ $data['worker']->name }} {{ $data['worker']->family_name }} от {{ $this->getMonthName() }}?"
                                                        class="mt-1 p-1 text-red-400 hover:text-red-600"
                                                        title="Премахни от месеца">
                                                    <x-heroicon-o-x-mark class="w-3 h-3" />
                                                </button>
                                            @endif
                                        </td>
                                        @for($day = 1; $day <= $this->getDaysInMonth(); $day++)
                                            @php
                                                $date = \Carbon\Carbon::create($year, $month, $day);
                                                $isWeekend = $date->isWeekend();
                                                $workerId = $data['worker']->id;
                                                $currentValue = $hoursData[$workerId][$day] ?? '';
                                                $vacationInfo = $vacationData[$workerId][$day] ?? null;
                                            @endphp
                                            <td class="px-1 py-2 whitespace-nowrap text-center {{ $isWeekend && !$vacationInfo ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                                                @if($vacationInfo)
                                                    @php $vacInfo = $this->getVacationTypeInfo($vacationInfo['type']); @endphp
                                                    <div class="relative w-12 h-8 mx-auto rounded border flex items-center justify-center"
                                                         style="{{ $vacInfo['style'] }}"
                                                         title="{{ $vacInfo['label'] }}{{ $vacationInfo['comment'] ? ': ' . $vacationInfo['comment'] : '' }}">
                                                        <span class="text-xs font-semibold">{{ $vacInfo['short'] }}</span>
                                                    </div>
                                                @elseif($isLocked)
                                                    <div class="text-xs {{ $currentValue ? 'text-gray-900 dark:text-gray-100' : 'text-gray-300 dark:text-gray-600' }}">
                                                        {{ $currentValue ?: '-' }}
                                                    </div>
                                                @else
                                                    <input type="number" 
                                                           wire:model.live="hoursData.{{ $workerId }}.{{ $day }}"
                                                           min="0" max="24" step="0.5"
                                                           class="w-12 h-8 text-xs text-center border-gray-300 dark:border-gray-600 rounded focus:border-indigo-500 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                                           placeholder="-">
                                                @endif
                                            </td>
                                        @endfor
                                        <td class="px-3 py-4 whitespace-nowrap text-center">
                                            <span class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                                                {{ number_format($data['calculated_price'] ?? 0, 2) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-center">
                                            <span class="text-sm font-semibold text-green-600 dark:text-green-400">
                                                {{ number_format($data['calculated_total'] ?? 0, 2) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Footer --}}
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-500 dark:text-gray-400 flex flex-wrap gap-4">
                            <span class="flex items-center">
                                <div class="w-3 h-3 bg-red-100 border border-red-300 rounded mr-2"></div>
                                Почивни дни
                            </span>
                            @foreach($this->getVacationTypesLegend() as $type => $info)
                                <span class="flex items-center">
                                    <div class="w-3 h-3 border rounded mr-2 flex items-center justify-center" style="{{ $info['style'] }}">
                                        <span class="text-xs font-bold">{{ $info['short'] }}</span>
                                    </div>
                                    {{ $info['label'] }}
                                </span>
                            @endforeach
                            @if(!$isLocked)
                                <span class="flex items-center">
                                    <x-heroicon-o-pencil class="w-3 h-3 mr-1 text-blue-500" />
                                    Редактируеми полета
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center space-x-2">
                            @if($hasUnsavedChanges && !$isLocked)
                                <span class="text-sm text-orange-600 dark:text-orange-400 flex items-center">
                                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 mr-1" />
                                    Незапазени промени
                                </span>
                            @endif
                            <button wire:click="exportMonthlyExcel" 
                                    class="inline-flex items-center px-3 py-2 border-0 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <x-heroicon-o-table-cells class="w-4 h-4 mr-1" />
                                Експорт Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- Empty State --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="text-center text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-users class="w-12 h-12 mx-auto mb-4 text-gray-300" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Няма работници за {{ $this->getMonthName() }}</h3>
                    <p class="mb-4">
                        Този месец няма записани работници. За да започнете работа с {{ $this->getMonthName() }}, 
                        добавете работници от списъка с активни служители.
                    </p>
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-4 text-left">
                        <div class="flex items-start">
                            <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 mr-2 flex-shrink-0" />
                            <div class="text-sm text-blue-700 dark:text-blue-200">
                                <strong>Как работи системата:</strong>
                                <ul class="mt-2 space-y-1">
                                    <li>• Всеки месец започва с празна таблица</li>
                                    <li>• Добавяте работници които ще работят този месец</li>
                                    <li>• Въвеждате часове за всеки ден</li>
                                    <li>• В края на месеца заключвате данните</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <a href="{{ url('/service/presences/monthly/' . $workplace . '/' . sprintf('%02d-%d', $this->month, $this->year) . '/workers/add') }}"
                       class="inline-flex items-center px-4 py-2 border-0 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                        Добави работници за {{ $this->getMonthName() }}
                    </a>
                </div>
            </div>
        @endif
    </div>

    {{-- Unsaved Changes Warning --}}
    @if($hasUnsavedChanges)
        <script>
            window.addEventListener('beforeunload', function (e) {
                e.preventDefault();
                e.returnValue = 'Имате незапазени промени. Сигурни ли сте, че искате да напуснете?';
            });
        </script>
    @endif
</x-filament-panels::page>
