<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\House;
use App\Models\Record;
use App\Policies\HousePolicy;
use App\Policies\RecordPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        House::class => HousePolicy::class,
        Record::class => RecordPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function ($user, string $ability) {
            if ($user instanceof \App\Models\Admin && $user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });
    }
}