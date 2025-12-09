<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Guava\FilamentKnowledgeBase\Filament\Panels\KnowledgeBasePanel;

class KnowledgeBasePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return KnowledgeBasePanel::make()
            ->viteTheme('resources/css/filament/knowledge-base/theme.css')
            ->guestAccess(); // Public access
    }
}
