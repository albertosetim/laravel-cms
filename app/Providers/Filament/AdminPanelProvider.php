<?php

namespace App\Providers\Filament;

use App\Filament\Livewire\SettingsModal;
use App\Filament\Pages\EditProfile;
use App\Http\Middleware\SetPanelLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
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
            ->profile(EditProfile::class, isSimple: false)
            ->maxContentWidth(Width::Full)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups([
                // Labels via closure → traduzidos por-request (casam com getNavigationGroup() dos resources).
                NavigationGroup::make(fn (): string => __('Content')),
                NavigationGroup::make(fn (): string => __('Structure')),
                NavigationGroup::make(fn (): string => __('System')),
                NavigationGroup::make(fn (): string => __('Developer tools')),
            ])
            // Botão de Settings no rodapé da sidebar. O modal é renderizado no fim
            // do <body> (BODY_END), fora do contexto transformado da sidebar, para
            // o overlay full-screen cobrir o viewport inteiro e não só o aside.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => (auth()->user()?->can('manageSettings') ?? false)
                    ? view('filament.settings-button')->render()
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => (auth()->user()?->can('manageSettings') ?? false)
                    ? Blade::render('@livewire($component)', ['component' => SettingsModal::class])
                    : '',
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
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
                SetPanelLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
