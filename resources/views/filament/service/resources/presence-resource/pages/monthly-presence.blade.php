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

        /* Weekend/holiday cells - RED background */
        .monthly-presence-table .weekend-cell {
            background-color: #fecaca !important; /* red-200 for light theme */
        }

        .dark .monthly-presence-table .weekend-cell {
            background-color: #991b1b !important; /* red-800 for dark theme */
        }

        /* Unsaved changes - YELLOW background */
        .monthly-presence-table .unsaved-cell {
            background-color: #fef08a !important; /* yellow-200 */
        }

        .dark .monthly-presence-table .unsaved-cell {
            background-color: #854d0e !important; /* yellow-800 for dark theme */
        }

        /* Input field styles for editable cells - make inputs visible */
        .monthly-presence-table input[type="number"] {
            background-color: transparent;
            color: #111827;
            border: 0;
            width: 230%;
            height: 100%;
            text-align: center;
            font-weight: 600;
            font-size: 0.75rem; /* text-xs */
        }

        .dark .monthly-presence-table input[type="number"] {
            color: #f9fafb;
        }

        .monthly-presence-table input[type="number"]:focus {
            outline: 2px solid #6366f1;
            outline-offset: -2px;
        }

        /* Locked cells display */
        .monthly-presence-table .locked-hours {
            font-weight: 600;
            font-size: 0.75rem;
        }

        /* Day cells - ensure consistent height and visibility */
        .monthly-presence-table td {
            min-height: 28px;
            height: 28px;
        }

        .monthly-presence-table tbody td {
            vertical-align: middle;
        }

        /* Ensure header text stays white in light theme */
        .monthly-presence-table thead th {
            color: #ffffff !important;
        }

        .dark .monthly-presence-table thead th {
            color: #ffffff !important;
        }
    </style>
@endpush

<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $formatPresenceNumber = function ($value, int $decimals = 2): string {
                $formatted = number_format((float) $value, $decimals, '.', '');
                $trimmed = rtrim(rtrim($formatted, '0'), '.');

                return $trimmed === '' ? '0' : $trimmed;
            };
        @endphp
        @if($monthlyData && $monthlyData->count() > 0)
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
                                :href="$this->getWorkplaceActivitiesUrl()"
                                color="primary"
                                icon="heroicon-o-cog-6-tooth"
                                size="sm"
                            >
                                Управлявай дейности
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

                            <x-filament::button
                                tag="a"
                                :href="$this->getConfigureHoursUrl()"
                                color="info"
                                icon="heroicon-o-clock"
                                size="sm"
                            >
                                Конфигурация часове
                            </x-filament::button>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto" data-monthly-presence-table-wrapper>
                    <table class="monthly-presence-table w-full text-xs border-collapse" data-monthly-presence-table>
                        <thead>
                            <tr class="bg-gray-800 dark:bg-gray-700 text-white" style="background: black;">
                                <th class="border border-gray-600 dark:border-gray-500 p-2 text-left min-w-[100px]">
                                    Длъжност
                                </th>
                                <th class="border border-gray-600 dark:border-gray-500 p-2 text-left min-w-[80px]">
                                    Заплата
                                </th>
                                <th class="border border-gray-600 dark:border-gray-500 p-2 text-left min-w-[100px]">
                                    Име
                                </th>
                                <th class="border border-gray-600 dark:border-gray-500 p-2 text-left min-w-[100px]">
                                    Фамилия
                                </th>
                                <th class="border border-gray-600 dark:border-gray-500 p-2 text-left min-w-[100px]">
                                    ЕГН
                                </th>
                                @for($day = 1; $day <= $this->getDaysInMonth(); $day++)
                                    @php
                                        $date = \Carbon\Carbon::create($year, $month, $day);
                                        $isWeekend = $date->isWeekend();
                                        $specialDay = $this->getSpecialDayInfo($day);
                                        $isOfficialHoliday = $specialDay && ($specialDay['type'] ?? null) === 1;
                                        $isNonWorking = $isOfficialHoliday || $isWeekend;
                                        $headerTitle = $specialDay['label'] ?? ($isWeekend ? 'Почивен ден' : '');
                                        $headerClasses = 'border border-gray-600 dark:border-gray-500 p-1 text-center min-w-[36px]';
                                        if ($isNonWorking) {
                                            $headerClasses .= ' bg-gray-300 text-gray-600';
                                        }
                                    @endphp
                                    <th class="{{ $headerClasses }}" title="{{ $headerTitle }}">
                                        {{ $day }}
                                    </th>
                                @endfor
                                <th class="border border-gray-600 dark:border-gray-500 p-2 text-center min-w-[80px]">
                                    Цена
                                </th>
                                <th class="border border-gray-600 dark:border-gray-500 p-2 text-center min-w-[80px]">
                                    Общо
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($monthlyData as $activityId => $activityGroup)
                                {{-- Activity Group Header Row --}}
                                <tr class="bg-gray-100 dark:bg-gray-700 font-semibold">
                                    <td class="border border-gray-400 dark:border-gray-600 p-1 text-left" title="{{ $activityGroup['activity_name'] }}">
                                        <span class="font-bold text-gray-900 dark:text-gray-100">
                                            {{ Str::limit($activityGroup['activity_name'], 8, '') }}
                                        </span>
                                    </td>
                                    <td class="border border-gray-400 dark:border-gray-600 p-1 text-right">
                                        <span class="font-bold text-gray-900 dark:text-gray-100">
                                            {{ number_format($activityGroup['activity_salary'], 0) }}
                                        </span>
                                    </td>
                                    <td class="border border-gray-400 dark:border-gray-600 p-1 text-left" colspan="3">
                                        <span class="font-bold text-gray-500 dark:text-gray-400">-</span>
                                    </td>
                                    @for($day = 1; $day <= $this->getDaysInMonth(); $day++)
                                        @php
                                            $date = \Carbon\Carbon::create($year, $month, $day);
                                            $isWeekend = $date->isWeekend();
                                            $specialDay = $this->getSpecialDayInfo($day);
                                            $isOfficialHoliday = $specialDay && ($specialDay['type'] ?? null) === 1;
                                            $isNonWorking = $isOfficialHoliday || $isWeekend;
                                            $cellTitle = $specialDay['label'] ?? ($isWeekend ? 'Почивен ден' : '');
                                            $cellBgClass = $isNonWorking ? 'bg-gray-200 dark:bg-gray-600' : '';
                                        @endphp
                                        <td class="border border-gray-400 dark:border-gray-600 p-1 text-center {{ $cellBgClass }}" title="{{ $cellTitle }}">
                                            <span class="font-bold {{ $isNonWorking ? 'text-red-600 dark:text-red-300' : 'text-gray-500 dark:text-gray-400' }}">-</span>
                                        </td>
                                    @endfor
                                    @php
                                        $usedBudget = $activityGroup['group_totals']['used_budget'] ?? 0;
                                        $maxBudget = $activityGroup['group_totals']['max_budget'] ?? 0;
                                        $usedHours = $activityGroup['group_totals']['used_hours'] ?? 0;
                                        $maxHours = $activityGroup['group_totals']['max_hours'] ?? 0;

                                        $budgetExceeded = $maxBudget !== null && $usedBudget > $maxBudget;
                                        $hoursExceeded = $maxHours !== null && $usedHours > $maxHours;

                                        $budgetCellClasses = $maxBudget === null
                                            ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                                            : ($budgetExceeded
                                                ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-200'
                                                : 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-200');

                                        $hoursCellClasses = $maxHours === null
                                            ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                                            : ($hoursExceeded
                                                ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-200'
                                                : 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-200');
                                    @endphp
                                    <td class="border border-gray-400 dark:border-gray-600 p-1 text-center {{ $budgetCellClasses }} whitespace-nowrap align-middle" style="min-width: 88px; background-color: palegreen;">
                                        <span class="font-semibold" style="font-size: 105%;">
                                            {{ $formatPresenceNumber($usedBudget) }}@if($maxBudget !== null) / {{ $formatPresenceNumber($maxBudget) }}@endif
                                        </span>
                                    </td>
                                    <td class="border border-gray-400 dark:border-gray-600 p-1 text-center {{ $hoursCellClasses }} whitespace-nowrap align-middle" style="min-width: 88px; background-color: palegreen;">
                                        <span class="font-semibold" style="font-size: 105%;">
                                            {{ $formatPresenceNumber($usedHours) }}@if($maxHours !== null) / {{ $formatPresenceNumber($maxHours) }}@endif
                                        </span>
                                    </td>
                                </tr>
                                
                                {{-- Individual Workers in this Activity --}}
                                @foreach($activityGroup['workers'] as $workerIndex => $data)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="border border-gray-400 dark:border-gray-600 p-1 text-left">
                                            <span class="text-gray-700 dark:text-gray-300">-</span>
                                        </td>
                                        <td class="border border-gray-400 dark:border-gray-600 p-1 text-right">
                                            <span class="text-gray-700 dark:text-gray-300">-</span>
                                        </td>
                                        <td class="border border-gray-400 dark:border-gray-600 p-1 text-left">
                                            <div class="flex items-center justify-between">
                                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $data['worker']->name }}</span>
                                                @if(!$isLocked)
                                                    <button wire:click="removeWorkerFromMonth({{ $data['worker']->id }})"
                                                            wire:confirm="Сигурни ли сте, че искате да премахнете {{ $data['worker']->name }} {{ $data['worker']->family_name }} от {{ $this->getMonthName() }}?"
                                                            class="ml-2 p-0.5 text-red-400 hover:text-red-600"
                                                            title="Премахни от месеца">
                                                        <x-heroicon-o-x-mark class="w-3 h-3" />
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="border border-gray-400 dark:border-gray-600 p-1 text-left">
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $data['worker']->family_name }}</span>
                                        </td>
                                        <td class="border border-gray-400 dark:border-gray-600 p-1 text-left">
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $data['worker']->egn }}</span>
                                        </td>
                                        @for($day = 1; $day <= $this->getDaysInMonth(); $day++)
                                            @php
                                                $date = \Carbon\Carbon::create($year, $month, $day);
                                                $isWeekend = $date->isWeekend();
                                                $workerId = $data['worker']->id;
                                                $currentValue = $hoursData[$activityId][$workerId][$day] ?? '';
                                                $vacationInfo = $vacationData[$workerId][$day] ?? null;
                                                $specialDay = $this->getSpecialDayInfo($day);
                                                $isOfficialHoliday = $specialDay && ($specialDay['type'] ?? null) === 1;
                                                $isNonWorking = $isOfficialHoliday || $isWeekend;
                                                $cellTitle = $specialDay['label'] ?? ($isWeekend ? 'Почивен ден' : '');

                                                // Get the original saved value from database
                                                $dayRecord = $data['records']->get($day);
                                                $originalValue = $dayRecord ? $dayRecord->hours : null;

                                                // Check if current value differs from original (unsaved change)
                                                $hasUnsavedChange = false;
                                                if (!$isLocked) {
                                                    // Convert both to strings for comparison to handle null/empty correctly
                                                    $currentStr = $currentValue === null || $currentValue === '' ? '' : (string)$currentValue;
                                                    $originalStr = $originalValue === null ? '' : (string)$originalValue;
                                                    $hasUnsavedChange = $currentStr !== $originalStr;
                                                }

                                                // Cell coloring: VACATION > YELLOW (unsaved) > RED (weekends/holidays)
                                                $cellBgClass = '';
                                                $vacInfo = null;
                                                if ($vacationInfo) {
                                                    // Vacation cells get vacation-specific background
                                                    $vacInfo = $this->getVacationTypeInfo($vacationInfo['type']);
                                                    $cellBgClass = 'vacation-cell';
                                                } elseif ($hasUnsavedChange) {
                                                    // YELLOW background for unsaved changes
                                                    $cellBgClass = 'unsaved-cell';
                                                } elseif ($isNonWorking) {
                                                    // RED background for weekend/holiday cells
                                                    $cellBgClass = 'weekend-cell';
                                                }

                                                // Prepare cell title with vacation info and/or special day
                                                $fullCellTitle = '';
                                                if ($vacInfo) {
                                                    $fullCellTitle = $vacInfo['label'] . ($vacationInfo['comment'] ? ': ' . $vacationInfo['comment'] : '');
                                                } elseif ($cellTitle) {
                                                    $fullCellTitle = $cellTitle;
                                                }
                                            @endphp
                                            <td class="border border-gray-400 dark:border-gray-600 p-1 text-center {{ $cellBgClass }}"
                                                @if($vacInfo) style="{{ $vacInfo['style'] }}" @endif
                                                title="{{ $fullCellTitle }}">
                                                @if($isLocked)
                                                    {{-- Locked month: show read-only value or vacation badge --}}
                                                    @if($vacInfo && (!$currentValue || $currentValue == ''))
                                                        {{-- Vacation day with no hours: show only badge --}}
                                                        <span class="text-xs font-bold">{{ $vacInfo['short'] }}</span>
                                                    @else
                                                        {{-- Regular day or vacation with hours: show value --}}
                                                        <span class="locked-hours">{{ $currentValue ?: '-' }}</span>
                                                    @endif
                                                @else
                                                    {{-- Editable: show input field or vacation badge --}}
                                                    @if($vacInfo && (!$currentValue || $currentValue == ''))
                                                        {{-- Vacation day with no hours: show only badge --}}
                                                        <span class="text-xs font-bold">{{ $vacInfo['short'] }}</span>
                                                    @else
                                                        {{-- Regular day or vacation with hours: show input --}}
                                                        <input type="number"
                                                               wire:model.live="hoursData.{{ $activityId }}.{{ $workerId }}.{{ $day }}"
                                                               min="0" max="24" step="0.5"
                                                               placeholder="-">
                                                    @endif
                                                @endif
                                            </td>
                                        @endfor
                                        <td class="border border-gray-400 dark:border-gray-600 p-1 text-center">
                                            <span class="font-semibold text-blue-600 dark:text-blue-400">
                                                {{ $formatPresenceNumber($data['calculated_price'] ?? 0) }}
                                            </span>
                                        </td>
                                        <td class="border border-gray-400 dark:border-gray-600 p-1 text-center">
                                            <span class="font-semibold text-green-600 dark:text-green-400">
                                                {{ $formatPresenceNumber($data['calculated_total'] ?? 0) }}
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
                                    color="success"
                                    icon="heroicon-o-check-circle"
                                    size="sm"
                                >
                                    Запазване
                                </x-filament::button>
                            @endif
                            <x-filament::button
                                wire:click="exportMonthlyExcel"
                                color="info"
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
