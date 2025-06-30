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
            // Future: Add Excel export button here
            // Actions\Action::make('export_excel')
            //     ->label('Експорт Excel')
            //     ->icon('heroicon-o-document-arrow-down')
            //     ->color('success'),
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
