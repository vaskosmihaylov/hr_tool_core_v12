<x-filament-panels::page>
    <div class="w-full space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg w-full">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Добавяне работник за месец <b>{{ $monthYear }}</b>
                    обект <b>{{ $workplaceModel->name ?? '—' }}</b>
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Изберете работник от региона и дейност, към която да бъде присъединен.
                </p>
            </div>

            <div class="px-6 py-6 space-y-6">
                <div class="flex items-center justify-between">
                    <x-filament::button
                        tag="a"
                        color="warning"
                        icon="heroicon-o-arrow-left"
                        class="mb-3"
                        :href="$this->getBackUrl()"
                    >
                        Назад
                    </x-filament::button>
                </div>

                @if(!$this->hasWorkers())
                    <div class="rounded-lg border border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-700 p-4 text-sm text-yellow-800 dark:text-yellow-200">
                        Няма активни работници в региона за избрания месец.
                    </div>
                @endif

                @if(!$this->hasActivities())
                    <div class="rounded-lg border border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700 p-4 text-sm text-blue-800 dark:text-blue-100">
                        Няма налични дейности за този обект. Управлявайте дейностите от
                        <a
                            href="{{ $this->getManageActivitiesUrl() }}"
                            class="font-medium text-blue-700 underline dark:text-blue-300"
                        >
                            страницата „Управление на дейности“
                        </a>
                        преди да продължите.
                    </div>
                @endif

                <form wire:submit.prevent="submit" class="space-y-6">
                    {{ $this->form }}

                    <div class="flex items-center justify-end gap-3">
                        <x-filament::button
                            type="submit"
                            icon="heroicon-o-check-circle"
                            color="primary"
                            :disabled="!$this->hasWorkers() || !$this->hasActivities()"
                        >
                            Създай
                        </x-filament::button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>
