<x-filament-panels::page>
    <div class="space-y-6">
        @if($isLocked)
            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <x-heroicon-o-lock-closed class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 mr-2 flex-shrink-0" />
                    <div class="text-sm text-red-700 dark:text-red-200">
                        <strong>Месецът е заключен.</strong> Не можете да променяте конфигурацията на часовете за заключен месец.
                        Отключете месеца от месечната таблица, за да правите промени.
                    </div>
                </div>
            </div>
        @else
            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 mr-2 flex-shrink-0" />
                    <div class="text-sm text-amber-700 dark:text-amber-200">
                        <strong>Внимание:</strong> Промените ще засегнат всички незаключени месеци.
                        Заключените месеци запазват стойностите си непроменени.
                        @if($isPastMonth)
                            <div class="mt-2 font-semibold text-amber-800 dark:text-amber-100">
                                ⚠ Редактирате незаключен минал месец. Промените ще променят и други незаключени месеци.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

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
                <fieldset @if($isLocked) disabled @endif>
                    {{ $this->form }}
                </fieldset>
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
