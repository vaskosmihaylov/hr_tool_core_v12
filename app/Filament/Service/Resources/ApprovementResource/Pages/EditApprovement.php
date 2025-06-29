<?php

namespace App\Filament\Service\Resources\ApprovementResource\Pages;

use App\Filament\Service\Resources\ApprovementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApprovement extends EditRecord
{
    protected static string $resource = ApprovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
