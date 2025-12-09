<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\HtmlString;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;

class ServicePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('service')
            ->path('/service')
            ->login()
            ->viteTheme('resources/css/filament/service/theme.css')
            ->brandName('FMS Services')
            ->brandLogo(fn () => new HtmlString(''))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/favicon.ico'))
            ->colors([
                'danger' => Color::Rose,
                'gray' => Color::Gray,
                'info' => Color::Blue,
                'primary' => Color::Red,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            ->discoverResources(in: app_path('Filament/Service/Resources'), for: 'App\\Filament\\Service\\Resources')
            ->discoverPages(in: app_path('Filament/Service/Pages'), for: 'App\\Filament\\Service\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Service/Widgets'), for: 'App\\Filament\\Service\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->maxContentWidth(MaxWidth::Full)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])

            ->authMiddleware([
                Authenticate::class,
            ])
            ->topNavigation()
            ->navigationGroups([
                '👥 Човешки ресурси',
                '🏢 Организация',
                '⏰ Присъствена форма',
                '✅ Одобрения',
                '📊 Отчети',
                '📂 Архив',
            ])
            ->renderHook(
                'panels::topbar.end',
                fn (): string => Blade::render('<x-filament::link
                    href="/documentation"
                    target="_blank"
                    icon="heroicon-o-book-open"
                    icon-size="lg"
                    class="fi-topbar-item"
                    style="margin-right: 1rem;"
                >
                    Ръководство
                </x-filament::link>')
            );
    }
}
