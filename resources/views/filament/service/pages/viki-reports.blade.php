<x-filament-panels::page>
    @if(!$this->showResults)
        <div class="space-y-6">
            <!-- Welcome Section -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-6">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100">
                            Модул за отчети на Viki Services
                        </h3>
                        <p class="text-blue-700 dark:text-blue-300 mt-1">
                            Генерирайте детайлни отчети за работно време и заплати със съвременен интерфейс
                        </p>
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center">
                        <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Как да започнете
                    </h4>
                    <ol class="list-decimal list-inside space-y-2 text-gray-600 dark:text-gray-400">
                        <li>Щракнете "Генерирай отчет" в горния ред</li>
                        <li>Изберете месец и година</li>
                        <li>Приложете филтри по нужда</li>
                        <li>Щракнете "Генерирай" за да видите резултатите</li>
                    </ol>
                </div>

                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center">
                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 00-2 2v0"></path>
                        </svg>
                        Налични функции
                    </h4>
                    <ul class="list-disc list-inside space-y-2 text-gray-600 dark:text-gray-400">
                        <li>Excel експорт на отчетите</li>
                        <li>Филтриране по регион, клиент, работно място</li>
                        <li>Роля-базиран достъп до данни</li>
                        <li>Интеграция с оригиналната система</li>
                    </ul>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Работници този месец</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->getNavigationBadge() }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0h3m2 0h5M9 7h6m-6 4h6m-2 4h2"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Текущ месец</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ now()->format('m/Y') }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Система версия</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Laravel 12</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- Results displayed when showResults is true -->
        <div class="space-y-6">
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-green-900 dark:text-green-100">
                    📊 Отчет генериран успешно!
                </h3>
                <p class="text-green-800 dark:text-green-200 mt-1">
                    Резултатите са показани по-долу. Използвайте бутоните за експорт в горния ред.
                </p>
            </div>
            
            <!-- Summary Statistics -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Обобщение на отчета</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ $reportData['summary']['total_workers'] ?? 0 }}</div>
                        <div class="text-sm text-gray-500">Работници</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">{{ number_format($reportData['summary']['total_hours'] ?? 0) }}</div>
                        <div class="text-sm text-gray-500">Часове</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600">{{ number_format($reportData['summary']['total_salary'] ?? 0, 2) }} лв.</div>
                        <div class="text-sm text-gray-500">Обща сума</div>
                    </div>
                </div>
            </div>

            <!-- Detailed Results Table -->
            @if(!empty($reportData['workerRecords']))
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Детайлни резултати за {{ $filters['month_id'] ?? 'N/A' }}/{{ $filters['year_id'] ?? 'N/A' }}
                    </h4>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Име</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Презиме</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Фамилия</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ЕГН</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Обект</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Клиент</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Регион</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Изработени часове</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Бонус</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Наказание</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Сума</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Сума + бонус - наказание</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($reportData['workerRecords'] as $record)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ $record->name ?? '' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ $record->middle_name ?? '' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ $record->family_name ?? '' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ $record->egn ?? '' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ $record->workPlaceName ?? '' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    @php
                                        $client = $record->clId ? \viki\Service\Models\Elequent\Client::find($record->clId) : null;
                                    @endphp
                                    {{ $client->name ?? '' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    @php
                                        $region = $record->regId ? \viki\Service\Models\Elequent\Region::find($record->regId) : null;
                                    @endphp
                                    {{ $region->name ?? '' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ $record->total ?? 0 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ number_format($reportData['bonusData'][$record->ID] ?? 0, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ number_format($reportData['penaltyData'][$record->ID] ?? 0, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ number_format($reportData['arraySum'][$record->ID] ?? 0, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                    @php
                                        $baseSalary = $reportData['arraySum'][$record->ID] ?? 0;
                                        $bonus = $reportData['bonusData'][$record->ID] ?? 0;
                                        $penalty = $reportData['penaltyData'][$record->ID] ?? 0;
                                        $totalWithBonus = $baseSalary + $bonus - $penalty;
                                    @endphp
                                    {{ number_format($totalWithBonus, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    Общо:
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ number_format($reportData['summary']['total_hours'] ?? 0) }}
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ number_format($reportData['summary']['total_bonus'] ?? 0, 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ number_format($reportData['summary']['total_penalty'] ?? 0, 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ number_format($reportData['summary']['total_salary'] ?? 0, 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    @php
                                        $totalSalary = $reportData['summary']['total_salary'] ?? 0;
                                        $totalBonus = $reportData['summary']['total_bonus'] ?? 0;
                                        $totalPenalty = $reportData['summary']['total_penalty'] ?? 0;
                                        $grandTotal = $totalSalary + $totalBonus - $totalPenalty;
                                    @endphp
                                    {{ number_format($grandTotal, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Pagination info -->
                @if(count($reportData['workerRecords']) > 50)
                <div class="px-6 py-3 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        Показани {{ count($reportData['workerRecords']) }} записа
                    </p>
                </div>
                @endif
            </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
