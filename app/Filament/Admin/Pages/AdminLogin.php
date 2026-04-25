<?php

namespace App\Filament\Admin\Pages;

use Filament\Auth\Pages\Login;
use Illuminate\Contracts\Support\Htmlable;

class AdminLogin extends Login
{
    protected static string $layout = 'filament.admin.layout.habimate-simple';

    public function getTitle(): string | Htmlable
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getTitle();
        }

        return __('Sign in');
    }

    public function getHeading(): string | Htmlable | null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getHeading();
        }

        return 'Welcome';
    }

    public function getSubheading(): string | Htmlable | null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        if (! filament()->hasRegistration()) {
            return 'Sign in to manage your space.';
        }

        return parent::getSubheading();
    }
}
