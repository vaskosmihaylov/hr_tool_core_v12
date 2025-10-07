<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $workplaceModel->name ?? 'Неизвестен обект' }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Настройки за месец {{ sprintf('%02d-%d', $month, $year) }}
                    </p>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Управление на временните дейности за присъствената форма
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Труд-дейност
                            </th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Основна
                            </th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Брой работници
                            </th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Обща цена (за един)
                            </th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Часове
                            </th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Опции
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($activities as $activity)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $activity->activity }}
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $activity->copied ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-900/40 dark:text-gray-300' }}">
                                        {{ $activity->copied ? 'да' : 'не' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-gray-100">
                                    {{ $activity->worker_count ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-gray-100">
                                    {{ number_format(($activity->neto_salary + $activity->social_plus), 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-gray-100">
                                    {{ $hoursByActivity[$activity->id] ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center justify-center gap-2">
                                        <x-filament::button
                                            size="sm"
                                            icon="heroicon-o-pencil-square"
                                            tag="a"
                                            color="primary"
                                            :href="sprintf('/service/presences/config/%d/%s/activity/%d', $workplace, sprintf('%02d-%d', $month, $year), $activity->id)"
                                            title="Редактирай дейност"
                                        >
                                            Редактирай
                                        </x-filament::button>

                                        @if($activity->date)
                                            <x-filament::button
                                                size="sm"
                                                color="danger"
                                                icon="heroicon-o-trash"
                                                x-data
                                                x-on:click.prevent="if (confirm('Сигурни ли сте, че искате да изтриете дейността?')) { $wire.deleteActivity({{ $activity->id }}) }"
                                            >
                                                Изтрий
                                            </x-filament::button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Няма конфигурирани дейности за избрания месец.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
