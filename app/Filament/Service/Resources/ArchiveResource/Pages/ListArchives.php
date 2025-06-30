<?php

namespace App\Filament\Service\Resources\ArchiveResource\Pages;

use App\Filament\Service\Resources\ArchiveResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArchives extends ListRecords
{
    protected static string $resource = ArchiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('help')
                ->label('Помощ')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Архив - Помощ')
                ->modalContent(view('filament.service.archive.help-modal'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Затвори'),
        ];
    }

    public function getTitle(): string
    {
        return 'Архив на присъствени форми';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Could add archive statistics widgets here
        ];
    }
}
