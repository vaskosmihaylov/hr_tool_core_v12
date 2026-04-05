<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4">
            <div class="flex items-start">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 mr-2 flex-shrink-0" />
                <div class="text-sm text-amber-700 dark:text-amber-200">
                    <strong>Внимание:</strong> Промените по дейностите (заплата, брой работници, име)
                    ще засегнат всички <strong>незаключени</strong> месеци. Заключените месеци запазват стойностите си непроменени.
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ $this->record->name }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Клиент: <strong>{{ $this->record->client?->name ?? '—' }}</strong> · Регион: <strong>{{ $this->record->region?->name ?? '—' }}</strong>
                </p>
            </div>
            <div class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/60 px-3 py-1 text-emerald-700 dark:text-emerald-200">
                    <span aria-hidden="true">💰</span>
                    {{ number_format($this->record->budget ?? 0, 2, ',', ' ') }} лв
                </span>
                <a href="{{ \App\Filament\Service\Resources\WorkPlaceResource::getUrl('edit', ['record' => $this->record]) }}"
                   class="inline-flex items-center gap-2 rounded-md border border-primary-200 dark:border-primary-700 px-3 py-2 text-primary-700 dark:text-primary-200 text-sm font-semibold hover:bg-primary-50 dark:hover:bg-primary-900/30">
                    <span aria-hidden="true">✏️</span>
                    Редактирай обекта
                </a>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
