<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\AdminLogin;
use App\Filament\Admin\Widgets\DashboardKpisWidget;
use App\Filament\Admin\Widgets\RecentAuditActivityWidget;
use App\Filament\Admin\Widgets\SystemHealthWidget;
use App\Http\Middleware\RestrictAdminPanelByIp;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Auth\MultiFactor\App\AppAuthentication;
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
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
            ->authGuard('admin')
            ->login(AdminLogin::class)
            ->brandName('HabiMate')
            ->brandLogo(asset('images/habimate-icon.png'))
            ->brandLogoHeight('4.5rem')
            ->favicon(asset('images/habimate-icon.png'))
            ->profile(isSimple: false)
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: (bool) filter_var(
                env('FILAMENT_ADMIN_MFA_REQUIRED', false),
                FILTER_VALIDATE_BOOLEAN,
            ))
            ->colors([
                // Brand: coral CTA / links (matches mobile app)
                'primary' => Color::hex('#FF6A6A'),
                // Cool slate neutrals for sidebar, chrome, and inactive nav (app #0F172A / #1E293B family)
                'gray' => Color::hex('#475569'),
                // Semantic accents aligned with marketing (#2EC4B6) and clear status UX
                'success' => Color::hex('#22C55E'),
                'warning' => Color::hex('#F59E0B'),
                'danger' => Color::hex('#E15555'),
                'info' => Color::hex('#2EC4B6'),
            ])
            ->navigationGroups([
                'System',
                'Operations',
                'Finance',
                'Content',
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                AccountWidget::class,
                DashboardKpisWidget::class,
                RecentAuditActivityWidget::class,
                SystemHealthWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                RestrictAdminPanelByIp::class,
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
            ->plugins([
                FilamentShieldPlugin::make(),
            ])->spa();
    }
}
