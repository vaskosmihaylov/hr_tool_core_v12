<x-filament-panels::page>
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Нова дейност за {{ $workplaceModel->name ?? 'обекта' }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Месец {{ sprintf('%02d-%d', $month, $year) }}
                </p>
            </div>
            <div class="px-6 py-6">
                <form wire:submit.prevent="submit" class="space-y-6">
                    {{ $this->form }}

                    <div class="flex items-center justify-between">
                        <x-filament::button
                            tag="a"
                            color="gray"
                            icon="heroicon-o-arrow-left"
                            :href="sprintf('/service/presences/config/%d/%s', $workplace, $monthYear)"
                        >
                            Назад
                        </x-filament::button>

                        <x-filament::button type="submit" icon="heroicon-o-check-circle" color="primary">
                            Запази
                        </x-filament::button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>
