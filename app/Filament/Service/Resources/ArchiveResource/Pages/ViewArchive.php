<?php

namespace App\Filament\Service\Resources\ArchiveResource\Pages;

use App\Filament\Service\Resources\ArchiveResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewArchive extends ViewRecord
{
    protected static string $resource = ArchiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_original')
                ->label('Оригинален изглед')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn (): string => "/service/archive/{$this->record->work_place_id}/" . date('m-Y', strtotime($this->record->date)))
                ->openUrlInNewTab(),
        ];
    }

    public function getTitle(): string
    {
        return 'Архив за ' . $this->record->workplace->name . ' - ' . date('m.Y', strtotime($this->record->date));
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Could add specific archive widgets here
        ];
    }
}
