<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Workplace Selection --}}
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
                        Избрана дата
                    </label>
                    <div class="text-lg font-semibold text-indigo-600 dark:text-indigo-400">
                        {{ $selectedDate->format('d.m.Y') }}
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            ({{ $selectedDate->format('l') }})
                        </span>
                    </div>
                </div>

                <div class="text-right">
                    <span class="inline-flex rounded-md shadow-sm">
                        <button wire:click="changeDate(-1)" 
                                class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-l-md text-gray-700 bg-white hover:text-gray-500 focus:outline-none focus:border-blue-300 focus:shadow-outline-blue">
                            ← Предишен
                        </button>
                        <button wire:click="goToToday" 
                                class="inline-flex items-center px-3 py-2 border-t border-b border-gray-300 text-sm leading-4 font-medium text-gray-700 bg-white hover:text-gray-500 focus:outline-none focus:border-blue-300 focus:shadow-outline-blue">
                            Днес
                        </button>
                        <button wire:click="changeDate(1)" 
                                class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-r-md text-gray-700 bg-white hover:text-gray-500 focus:outline-none focus:border-blue-300 focus:shadow-outline-blue">
                            Следващ →
                        </button>
                    </span>
                </div>
            </div>
        </div>

        {{-- Presence Table --}}
        @if($workers && $workers->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Таблица за присъствие - {{ $selectedDate->format('d.m.Y') }}
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Работник
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    ЕГН
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Дейност
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Часове
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Статус
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($workers as $worker)
                                @php
                                    $presenceRecord = $presenceData->get($worker->id);
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $worker->name }} {{ $worker->middle_name }} {{ $worker->family_name }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $worker->egn }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $presenceRecord?->activity?->activity ?? 'Не е зададена' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($presenceRecord)
                                            <span class="text-sm font-medium text-green-600">
                                                {{ $presenceRecord->hours }} ч.
                                            </span>
                                        @else
                                            <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($presenceRecord)
                                            @switch($presenceRecord->status)
                                                @case(0)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                                        Чакащ
                                                    </span>
                                                    @break
                                                @case(1)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                        Одобрен
                                                    </span>
                                                    @break
                                                @case(2)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                                        Отхвърлен
                                                    </span>
                                                    @break
                                                @case(3)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                        Приключен
                                                    </span>
                                                    @break
                                            @endswitch
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                                Отсъства
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Общо работници: {{ $workers->count() }} | 
                            Присъстващи: {{ $presenceData->count() }} |
                            Общо часове: {{ $presenceData->sum('hours') }}
                        </div>
                        <div>
                            <button wire:click="exportExcel" 
                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                📊 Експорт Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="text-center text-gray-500 dark:text-gray-400">
                    <p>Няма работници за избраното работно място.</p>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
