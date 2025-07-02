<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Month Navigation --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Работно място
                    </label>
                    <select wire:model.live="workplace" wire:change="changeWorkplace" 
                            class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @foreach($workplaces as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Месец и година
                    </label>
                    <div class="text-lg font-semibold text-indigo-600 dark:text-indigo-400 dark:text-indigo-400">
                        {{ $this->getMonthName() }}
                    </div>
                </div>

                <div class="text-right">
                    <span class="inline-flex rounded-md shadow-sm">
                        <button wire:click="changeMonth(-1)" 
                                class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-l-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:text-gray-500 dark:text-gray-400 focus:outline-none focus:border-blue-300 focus:shadow-outline-blue">
                            ← Предишен
                        </button>
                        <button wire:click="goToCurrentMonth" 
                                class="inline-flex items-center px-3 py-2 border-t border-b border-gray-300 text-sm leading-4 font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:text-gray-500 dark:text-gray-400 focus:outline-none focus:border-blue-300 focus:shadow-outline-blue">
                            Текущ
                        </button>
                        <button wire:click="changeMonth(1)" 
                                class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-r-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:text-gray-500 dark:text-gray-400 focus:outline-none focus:border-blue-300 focus:shadow-outline-blue">
                            Следващ →
                        </button>
                    </span>
                </div>
            </div>
        </div>

        {{-- Monthly Statistics --}}
        @if($monthlyData && $monthlyData->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Общо работници</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $monthlyData->count() }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Общо часове</div>
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($monthlyData->sum('total_hours'), 1) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Средно часове/ден</div>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ $monthlyData->count() > 0 ? number_format($monthlyData->avg('average_hours'), 1) : 0 }}
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Дни в месеца</div>
                    <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $this->getDaysInMonth() }}</div>
                </div>
            </div>

            {{-- Monthly Table --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Месечен преглед за {{ $this->getMonthName() }}
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider sticky left-0 bg-gray-50 dark:bg-gray-700 dark:bg-gray-700">
                                    Работник
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Общо часове
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Работни дни
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Средно ч./ден
                                </th>
                                @for($day = 1; $day <= $this->getDaysInMonth(); $day++)
                                    <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[40px]">
                                        {{ $day }}
                                    </th>
                                @endfor
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($monthlyData as $data)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap sticky left-0 bg-white dark:bg-gray-800 dark:bg-gray-800">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $data['worker']->name }} {{ $data['worker']->family_name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $data['worker']->egn }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        <span class="text-sm font-semibold text-green-600 dark:text-green-400">
                                            {{ number_format($data['total_hours'], 1) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        <span class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                                            {{ $data['working_days'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        <span class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">
                                            {{ number_format($data['average_hours'], 1) }}
                                        </span>
                                    </td>
                                    @for($day = 1; $day <= $this->getDaysInMonth(); $day++)
                                        @php
                                            $dayRecord = $data['records']->get($day);
                                        @endphp
                                        <td class="px-2 py-4 whitespace-nowrap text-center">
                                            @if($dayRecord)
                                                <div class="text-xs font-medium
                                                    @switch($dayRecord->status)
                                                        @case(0) text-yellow-600 dark:text-yellow-400 @break
                                                        @case(1) text-green-600 dark:text-green-400 @break
                                                        @case(2) text-red-600 dark:text-red-400 @break
                                                        @case(3) text-blue-600 dark:text-blue-400 @break
                                                        @default text-gray-600 dark:text-gray-300
                                                    @endswitch
                                                ">
                                                    {{ $dayRecord->hours }}
                                                </div>
                                            @else
                                                <div class="text-xs text-gray-300 dark:text-gray-600">-</div>
                                            @endif
                                        </td>
                                    @endfor
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            <div class="flex space-x-4">
                                <span class="flex items-center">
                                    <div class="w-3 h-3 bg-yellow-200 rounded mr-2"></div>
                                    Чакащ одобрение
                                </span>
                                <span class="flex items-center">
                                    <div class="w-3 h-3 bg-green-200 rounded mr-2"></div>
                                    Одобрен
                                </span>
                                <span class="flex items-center">
                                    <div class="w-3 h-3 bg-red-200 rounded mr-2"></div>
                                    Отхвърлен
                                </span>
                                <span class="flex items-center">
                                    <div class="w-3 h-3 bg-blue-200 rounded mr-2"></div>
                                    Приключен
                                </span>
                            </div>
                        </div>
                        <div>
                            <button wire:click="exportMonthlyPdf" 
                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 mr-2">
                                📄 Месечен PDF
                            </button>
                            <button wire:click="exportMonthlyExcel" 
                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                📊 Месечен Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="text-center text-gray-500 dark:text-gray-400">
                    <p>Няма данни за избраното работно място и месец.</p>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
