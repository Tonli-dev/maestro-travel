<?php

namespace App\Providers\Filament;

use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Maestro Travel')
            ->brandLogo(asset('images/maestro-logo.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/favicon.svg'))
            ->font('Arial', provider: LocalFontProvider::class)
            ->darkMode(false)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->databaseTransactions()
            ->sidebarWidth('15rem')
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => array_replace(Color::hex('#203c72'), [
                    50 => '#eef1f6',
                    100 => '#e4eaf3',
                    600 => '#203c72',
                    700 => '#192f5b',
                    800 => '#15284b',
                    900 => '#192d49',
                    950 => '#10213a',
                ]),
                'gray' => [
                    50 => '#faf9f5',
                    100 => '#f1f1eb',
                    200 => '#dce0da',
                    300 => '#bcc6d2',
                    400 => '#8995a4',
                    500 => '#65717e',
                    600 => '#56636d',
                    700 => '#43546b',
                    800 => '#2c405b',
                    900 => '#192d49',
                    950 => '#192d49',
                ],
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
