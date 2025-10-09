@push('styles')
    <style>
        .monthly-presence-floating-header {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 60;
            visibility: hidden;
            pointer-events: none;
            background-color: inherit;
        }

        .monthly-presence-floating-header thead tr {
            background-color: inherit;
        }

        .monthly-presence-floating-header th {
            background-color: inherit;
        }

        .monthly-presence-table .day-off {
            background-color: #dc2626 !important;
            color: #ffffff !important;
        }

        .dark .monthly-presence-table .day-off {
            background-color: #b91c1c !important;
            color: #ffffff !important;
        }
    </style>
@endpush

<x-filament-panels::page>
    <div class="space-y-6">
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
                    <div class="flex flex-col gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Редактируема таблица за {{ $this->getMonthName() }}
                            </h3>
                            @if($isLocked)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    <x-heroicon-s-lock-closed class="w-3 h-3 mr-1" />
                                    Месецът е заключен
                                </span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2 justify-end md:self-end">
                            <x-filament::button
                                tag="a"
                                :href="$this->getConfigureActivitiesUrl()"
                                color="primary"
                                icon="heroicon-o-cog-6-tooth"
                                size="sm"
                            >
                                Конфигурирай дейности
                            </x-filament::button>

                            <x-filament::button
                                tag="a"
                                :href="$this->getManageWorkersUrl()"
                                color="warning"
                                icon="heroicon-o-users"
                                size="sm"
                            >
                                Управление работници
                            </x-filament::button>

                            @if(!$isLocked)
                                <x-filament::button
                                    wire:click="saveHours"
                                    :disabled="!$hasUnsavedChanges"
                                    color="success"
                                    icon="heroicon-o-check"
                                    size="sm"
                                >
                                    Запази часовете
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto" data-monthly-presence-table-wrapper>
                    <table class="monthly-presence-table min-w-full divide-y divide-gray-200 dark:divide-gray-700" data-monthly-presence-table>
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-4 text-center text-base font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wide min-w-[120px]">
                                    Длъжност
                                </th>
                                <th class="px-3 py-4 text-center text-base font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wide min-w-[80px]">
                                    Заплата
                                </th>
                                <th class="px-3 py-4 text-left text-base font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wide min-w-[100px]">
                                    Име
                                </th>
                                <th class="px-3 py-4 text-left text-base font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wide min-w-[100px]">
                                    Фамилия
                                </th>
                                <th class="px-3 py-4 text-center text-base font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wide min-w-[100px]">
                                    ЕГН
                                </th>
                                @for($day = 1; $day <= $this->getDaysInMonth(); $day++)
                                    @php
                                        $date = \Carbon\Carbon::create($year, $month, $day);
                                        $isWeekend = $date->isWeekend();
                                        $specialDay = $this->getSpecialDayInfo($day);
                                        $isOfficialHoliday = $specialDay && ($specialDay['type'] ?? null) === 1;
                                        $isNonWorking = $isOfficialHoliday || $isWeekend;
                                        $headerClasses = [
                                            'px-1 py-3 text-center text-base font-semibold uppercase tracking-wide min-w-[50px] transition-colors text-gray-700 dark:text-gray-200',
                                        ];
                                        $headerTitle = $specialDay['label'] ?? ($isWeekend ? 'Почивен ден' : '');

                                        if ($isNonWorking) {
                                            $headerClasses[] = 'text-red-600 dark:text-red-300';
                                        }
                                    @endphp
                                    <th class="{{ implode(' ', $headerClasses) }}" title="{{ $headerTitle }}">
                                        <div>{{ $day }}</div>
                                        <div class="text-xs font-normal">{{ $date->format('D') }}</div>
                                    </th>
                                @endfor
                                <th class="px-3 py-4 text-center text-base font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wide min-w-[80px]">
                                    Цена
                                </th>
                                <th class="px-3 py-4 text-center text-base font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wide min-w-[80px]">
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
                                            $specialDay = $this->getSpecialDayInfo($day);
                                            $isOfficialHoliday = $specialDay && ($specialDay['type'] ?? null) === 1;
                                            $isNonWorking = $isOfficialHoliday || $isWeekend;
                                            $cellClasses = ['px-1 py-2 whitespace-nowrap text-center'];
                                            $cellTitle = $specialDay['label'] ?? ($isWeekend ? 'Почивен ден' : '');

                                        if ($isNonWorking) {
                                            $cellClasses[] = 'text-red-600 dark:text-red-300';
                                        }
                                    @endphp
                                        <td class="{{ implode(' ', $cellClasses) }}" title="{{ $cellTitle }}">
                                            <div class="text-xs font-bold {{ $isNonWorking ? 'text-red-600 dark:text-red-300' : 'text-gray-500 dark:text-gray-400' }}">-</div>
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
                                                $specialDay = $this->getSpecialDayInfo($day);
                                                $isOfficialHoliday = $specialDay && ($specialDay['type'] ?? null) === 1;
                                                $isNonWorking = $isOfficialHoliday || $isWeekend;
                                                $cellClasses = ['px-1 py-2 whitespace-nowrap text-center'];
                                                $cellTitle = $specialDay['label'] ?? ($isWeekend ? 'Почивен ден' : '');

                                                if ($isNonWorking) {
                                                    $cellClasses[] = 'day-off';
                                                    $cellClasses[] = 'bg-red-600 text-white dark:bg-red-700';
                                                }
                                            @endphp
                                            <td class="{{ implode(' ', $cellClasses) }}" title="{{ $cellTitle }}">
                                                @if($vacationInfo)
                                                    @php $vacInfo = $this->getVacationTypeInfo($vacationInfo['type']); @endphp
                                                    <div class="relative w-12 h-8 mx-auto rounded border flex items-center justify-center"
                                                         style="{{ $vacInfo['style'] }}"
                                                         title="{{ $vacInfo['label'] }}{{ $vacationInfo['comment'] ? ': ' . $vacationInfo['comment'] : '' }}">
                                                        <span class="text-xs font-semibold">{{ $vacInfo['short'] }}</span>
                                                    </div>
                                                @elseif($isLocked)
                                                    <div class="inline-flex w-12 h-8 items-center justify-center text-xs {{ $isNonWorking ? 'text-white font-semibold' : ($currentValue ? 'text-gray-900 dark:text-gray-100' : 'text-gray-300 dark:text-gray-600') }}">
                                                        {{ $currentValue ?: '-' }}
                                                    </div>
                                                @else
                                                    <input type="number" 
                                                           wire:model.live="hoursData.{{ $workerId }}.{{ $day }}"
                                                           min="0" max="24" step="0.5"
                                                           class="w-12 h-8 text-sm text-center border-gray-300 dark:border-gray-600 rounded focus:border-indigo-500 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
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
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
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
                        <div class="flex flex-wrap items-center gap-2 justify-end">
                            @if($hasUnsavedChanges && !$isLocked)
                                <span class="text-sm text-orange-600 dark:text-orange-400 flex items-center">
                                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 mr-1" />
                                    Незапазени промени
                                </span>
                            @endif
                            @if(!$isLocked)
                                <x-filament::button
                                    wire:click="saveHours"
                                    :disabled="!$hasUnsavedChanges"
                                    color="success"
                                    icon="heroicon-o-check"
                                    size="sm"
                                >
                                    Запази часовете
                                </x-filament::button>
                            @endif
                            <x-filament::button
                                wire:click="exportMonthlyExcel"
                                color="primary"
                                icon="heroicon-o-table-cells"
                                size="sm"
                            >
                                Експорт Excel
                            </x-filament::button>
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
                    <x-filament::button
                        tag="a"
                        :href="$this->getManageWorkersUrl()"
                        color="primary"
                        icon="heroicon-o-plus"
                    >
                        Добави работници за {{ $this->getMonthName() }}
                    </x-filament::button>
                </div>
            </div>
        @endif
    </div>

    {{-- Unsaved Changes Warning --}}
    @if($hasUnsavedChanges)
        @push('scripts')
            <script>
                window.addEventListener('beforeunload', function (e) {
                    e.preventDefault();
                    e.returnValue = 'Имате незапазени промени. Сигурни ли сте, че искате да напуснете?';
                });
            </script>
        @endpush
    @endif
</x-filament-panels::page>

@push('scripts')
    <script>
        const initializeMonthlyPresenceFloatingHeader = () => {
            if (window.cleanupMonthlyPresenceFloatingHeader) {
                window.cleanupMonthlyPresenceFloatingHeader();
            }

            const wrapper = document.querySelector('[data-monthly-presence-table-wrapper]');
            const table = wrapper?.querySelector('[data-monthly-presence-table]');
            const originalHead = table?.querySelector('thead');

            if (!wrapper || !table || !originalHead) {
                return;
            }

            const topbar = document.querySelector('.fi-topbar');
            const offset = topbar ? topbar.getBoundingClientRect().height : 0;

            let floatingTable = document.getElementById('monthly-presence-floating-header');
            if (floatingTable) {
                floatingTable.remove();
            }

            floatingTable = table.cloneNode(false);
            floatingTable.id = 'monthly-presence-floating-header';
            floatingTable.className = table.className + ' monthly-presence-floating-header bg-white dark:bg-gray-700';
            const clonedHead = originalHead.cloneNode(true);
            floatingTable.appendChild(clonedHead);
            document.body.appendChild(floatingTable);

            const cleanupFns = [];

            const updateFloatingMetrics = () => {
                const wrapperRect = wrapper.getBoundingClientRect();
                floatingTable.style.width = `${table.offsetWidth}px`;
                floatingTable.style.left = `${wrapperRect.left}px`;
            };

            const alignFloatingHeader = () => {
                floatingTable.style.transform = `translateX(${-wrapper.scrollLeft}px)`;
            };

            const syncColumnWidths = () => {
                const originalCells = Array.from(originalHead.querySelectorAll('th'));
                const clonedCells = Array.from(clonedHead.querySelectorAll('th'));

                if (originalCells.length !== clonedCells.length) {
                    return;
                }

                originalCells.forEach((cell, index) => {
                    const width = cell.getBoundingClientRect().width;
                    clonedCells[index].style.width = `${width}px`;
                });

                updateFloatingMetrics();
                alignFloatingHeader();
            };

            const updateVisibility = () => {
                const tableRect = table.getBoundingClientRect();
                const headerHeight = originalHead.getBoundingClientRect().height;

                if (tableRect.top < offset && tableRect.bottom > offset + headerHeight) {
                    floatingTable.style.visibility = 'visible';
                    floatingTable.style.top = `${offset}px`;
                    updateFloatingMetrics();
                    alignFloatingHeader();
                } else {
                    floatingTable.style.visibility = 'hidden';
                }
            };

            const onWrapperScroll = () => {
                alignFloatingHeader();
                updateVisibility();
            };

            const onWindowScroll = () => updateVisibility();
            const onWindowResize = () => {
                syncColumnWidths();
                updateVisibility();
            };

            syncColumnWidths();
            updateVisibility();

            wrapper.addEventListener('scroll', onWrapperScroll);
            window.addEventListener('scroll', onWindowScroll, { passive: true });
            window.addEventListener('resize', onWindowResize);

            cleanupFns.push(() => wrapper.removeEventListener('scroll', onWrapperScroll));
            cleanupFns.push(() => window.removeEventListener('scroll', onWindowScroll));
            cleanupFns.push(() => window.removeEventListener('resize', onWindowResize));
            cleanupFns.push(() => floatingTable.remove());

            window.cleanupMonthlyPresenceFloatingHeader = () => {
                cleanupFns.forEach((fn) => fn());
                window.cleanupMonthlyPresenceFloatingHeader = null;
            };
        };

        document.addEventListener('DOMContentLoaded', () => {
            requestAnimationFrame(initializeMonthlyPresenceFloatingHeader);
        });

        document.addEventListener('livewire:navigated', () => {
            requestAnimationFrame(initializeMonthlyPresenceFloatingHeader);
        });
    </script>
@endpush
