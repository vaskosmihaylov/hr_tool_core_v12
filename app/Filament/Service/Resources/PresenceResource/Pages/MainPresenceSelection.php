<?php

namespace App\Filament\Service\Resources\PresenceResource\Pages;

use App\Filament\Service\Resources\PresenceResource;
use Filament\Resources\Pages\Page;
use Viki\Service\Models\Elequent\WorkPlace;
use Viki\Service\Models\Elequent\VikiUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class MainPresenceSelection extends Page
{
    protected static string $resource = PresenceResource::class;
    protected static string $view = 'filament.service.resources.presence-resource.pages.main-presence-selection';
    protected static ?string $title = 'Присъствена форма';

    private const BULGARIAN_MONTHS = [
        '01' => 'Януари', '02' => 'Февруари', '03' => 'Март', '04' => 'Април',
        '05' => 'Май', '06' => 'Юни', '07' => 'Юли', '08' => 'Август',
        '09' => 'Септември', '10' => 'Октомври', '11' => 'Ноември', '12' => 'Декември'
    ];

    public $workplaces = [];
    public $months = [];
    
    public function mount(): void
    {
        $this->loadWorkplaces();
        $this->loadMonths();
    }

    private function loadWorkplaces(): void
    {
        $user = VikiUser::find(Auth::id());
        
        $workplaces = match(true) {
            Auth::user()->hasRole(['admin', 'super_admin']) => 
                WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)->orderBy('name')->get(),
            Auth::user()->hasRole('manager') => 
                WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)
                    ->whereIn('region_id', VikiUser::getCurrentUserRegionId(Auth::id()))
                    ->orderBy('name')->get(),
            Auth::user()->hasRole('supervisor') => 
                $user->activeWorkPlaces()->orderBy('name')->get(),
            default => collect()
        };

        $this->workplaces = $workplaces->pluck('name', 'id')->toArray();
    }

    private function loadMonths(): void
    {
        $this->months = [];
        for ($i = -1; $i <= 1; $i++) {
            $date = Carbon::now()->addMonths($i);
            $key = $date->format('m-Y');
            $monthNumber = $date->format('m');
            $value = self::BULGARIAN_MONTHS[$monthNumber] . ' ' . $date->format('Y');
            $this->months[$key] = $value;
        }
    }

    public function getBulgarianMonthName(string $month): string
    {
        return self::BULGARIAN_MONTHS[$month] ?? $month;
    }
}
