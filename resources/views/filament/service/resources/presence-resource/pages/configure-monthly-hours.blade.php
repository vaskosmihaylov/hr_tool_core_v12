<x-filament-panels::page>
    <div class="space-y-6">
        @if($activities->count() > 0)
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 mr-2 flex-shrink-0" />
                    <div class="text-sm text-blue-700 dark:text-blue-200">
                        <strong>Информация:</strong>
                        <ul class="mt-2 space-y-1">
                            <li>• Конфигурирайте очакваните работни часове за всяка дейност от тип "Сумарно" (почасово)</li>
                            <li>• Часовете се използват за изчисляване на цената на час: <strong>Месечна заплата / Часове = Цена на час</strong></li>
                            <li>• Ако часовете не са конфигурирани, системата ще използва стандартния работен месец (160 часа)</li>
                            <li>• Промените ще се отразят веднага в месечната таблица за присъствие</li>
                        </ul>
                    </div>
                </div>
            </div>

            <form wire:submit="save">
                {{ $this->form }}
            </form>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="text-center text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-clock class="w-12 h-12 mx-auto mb-4 text-gray-300" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                        Няма дейности от тип "Сумарно"
                    </h3>
                    <p class="mb-4">
                        За {{ $this->getMonthName() }} няма дефинирани дейности от тип "Сумарно" (почасово).
                        Всички дейности са от тип "Стандартно" и не изискват конфигурация на часове.
                    </p>
                    <x-filament::button
                        tag="a"
                        :href="$this->getBackUrl()"
                        color="gray"
                        icon="heroicon-o-arrow-left"
                    >
                        Обратно към месечна таблица
                    </x-filament::button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
