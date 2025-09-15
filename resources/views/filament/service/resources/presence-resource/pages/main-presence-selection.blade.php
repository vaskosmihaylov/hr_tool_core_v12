<x-filament-panels::page>
    <style>
        .force-green-button {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%) !important;
            border: 3px solid #16a34a !important;
            color: white !important;
            box-shadow: 0 10px 30px rgba(34, 197, 94, 0.3) !important;
        }
        .force-green-button:hover {
            transform: scale(1.05) !important;
            box-shadow: 0 15px 40px rgba(34, 197, 94, 0.5) !important;
        }
    </style>
    <div class="space-y-6">
        {{-- Welcome Header --}}
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 rounded-lg shadow p-8">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 dark:bg-green-900 mb-4">
                    <x-heroicon-o-clock class="h-8 w-8 text-green-600 dark:text-green-400" />
                </div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                    Присъствена форма
                </h2>
                <p class="text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                    Управление на присъствието и работните часове на служителите. 
                    Изберете обект и месец за да започнете конфигурирането.
                </p>
            </div>
        </div>

        {{-- Simple Form (No Livewire) --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                    <x-heroicon-o-building-office class="h-5 w-5 mr-2 text-blue-500" />
                    Избор на обект и месец
                </h3>
            </div>

            <form method="GET" action="/service/presence-configure" class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Workplace Selection --}}
                    <div>
                        <label for="workplace_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Обект ({{ count($workplaces) }} налични)
                        </label>
                        <select name="workplace_id" id="workplace_id" required
                                class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">Изберете обект...</option>
                            @foreach($workplaces as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Month Selection --}}
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Месец
                        </label>
                        <select name="date" id="date" required
                                class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            @foreach($months as $key => $name)
                                <option value="{{ $key }}" 
                                        @if($key === now()->format('m-Y')) selected @endif>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Submit Button --}}
                <div class="flex justify-center pt-4">
                    <button type="submit" 
                            style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%) !important; 
                                   border: 3px solid #16a34a !important; 
                                   color: white !important; 
                                   box-shadow: 0 10px 30px rgba(34, 197, 94, 0.3) !important;
                                   font-weight: 900 !important;
                                   font-size: 1.25rem !important;
                                   padding: 1.25rem 2.5rem !important;
                                   border-radius: 0.75rem !important;
                                   transition: all 0.3s ease !important;
                                   display: inline-flex !important;
                                   align-items: center !important;"
                            onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 15px 40px rgba(34, 197, 94, 0.5)'"
                            onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 10px 30px rgba(34, 197, 94, 0.3)'"
                            class="force-green-button inline-flex items-center">
                        <x-heroicon-o-cog class="h-5 w-5 mr-2" />
                        Конфигурирай месеца
                    </button>
                </div>
            </form>
        </div>



        {{-- Instructions --}}
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <x-heroicon-o-information-circle class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-medium text-blue-900 dark:text-blue-100">
                        Как да използвате присъствената форма
                    </h3>
                    <div class="mt-2 text-blue-700 dark:text-blue-200 space-y-2">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-0.5">
                                <div class="h-2 w-2 bg-blue-400 rounded-full"></div>
                            </div>
                            <div class="ml-3">
                                <strong>Стъпка 1:</strong> Изберете обект от списъка ({{ count($workplaces) }} налични)
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-0.5">
                                <div class="h-2 w-2 bg-blue-400 rounded-full"></div>
                            </div>
                            <div class="ml-3">
                                <strong>Стъпка 2:</strong> Изберете месец за управление
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-0.5">
                                <div class="h-2 w-2 bg-blue-400 rounded-full"></div>
                            </div>
                            <div class="ml-3">
                                <strong>Стъпка 3:</strong> Натиснете "Конфигурирай месеца"
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
