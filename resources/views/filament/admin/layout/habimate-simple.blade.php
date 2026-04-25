@php
    use Filament\Support\Enums\Width;

    $livewire ??= null;

    $renderHookScopes = $livewire?->getRenderHookScopes();
    $maxContentWidth ??= (filament()->getSimplePageMaxContentWidth() ?? Width::Large);

    if (is_string($maxContentWidth)) {
        $maxContentWidth = Width::tryFrom($maxContentWidth) ?? $maxContentWidth;
    }
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    @props([
        'after' => null,
        'heading' => null,
        'subheading' => null,
    ])

    <div class="habimate-login-root fi-simple-layout">
        <style>
            .habimate-login-root {
                min-height: 100vh;
                background: linear-gradient(165deg, #FF8E8E 0%, #FF6A6A 38%, #0F172A 100%);
                position: relative;
                overflow-x: hidden;
            }
            .habimate-login-root::before,
            .habimate-login-root::after {
                content: '';
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.12);
                pointer-events: none;
                z-index: 0;
            }
            .habimate-login-root::before {
                width: min(80vw, 28rem);
                height: min(80vw, 28rem);
                top: -6rem;
                right: -5rem;
            }
            .habimate-login-root::after {
                width: min(100vw, 36rem);
                height: min(100vw, 36rem);
                bottom: -10rem;
                left: -8rem;
            }
            .habimate-login-root .fi-simple-main-ctn {
                position: relative;
                z-index: 1;
                padding: 2rem 1rem;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .habimate-login-root .fi-simple-main {
                width: 100%;
                max-width: 28rem;
            }
            .habimate-login-root .fi-simple-page {
                background: rgba(255, 255, 255, 0.22);
                border: 1px solid rgba(255, 255, 255, 0.45);
                border-radius: 2.5rem;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                padding: 1.75rem 1.5rem 2rem;
            }
            .habimate-login-root .fi-simple-header {
                margin-bottom: 1.25rem;
            }
            .habimate-login-root .fi-simple-header-heading {
                color: #1e293b !important;
                font-weight: 900;
                letter-spacing: -0.04em;
            }
            .habimate-login-root .fi-simple-header-subheading {
                color: #475569 !important;
                font-weight: 600;
            }
            .habimate-login-root .fi-logo img {
                border-radius: 1.25rem;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
                background: #fff;
                padding: 0.35rem;
            }
        </style>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        @if (($hasTopbar ?? true) && filament()->auth()->check())
            <div class="fi-simple-layout-header">
                @if (filament()->hasDatabaseNotifications())
                    @livewire(filament()->getDatabaseNotificationsLivewireComponent(), [
                        'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                        'position' => \Filament\Enums\DatabaseNotificationsPosition::Topbar,
                    ])
                @endif

                @if (filament()->hasUserMenu())
                    @livewire(Filament\Livewire\SimpleUserMenu::class)
                @endif
            </div>
        @endif

        <div class="fi-simple-main-ctn">
            <main
                @class([
                    'fi-simple-main',
                    ($maxContentWidth instanceof Width) ? "fi-width-{$maxContentWidth->value}" : $maxContentWidth,
                ])
            >
                {{ $slot }}
            </main>
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
    </div>
</x-filament-panels::layout.base>
