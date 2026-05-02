<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Shield policies - using correct model namespaces
        Gate::policy(\viki\Service\Models\Elequent\Region::class, \App\Policies\RegionPolicy::class);
        Gate::policy(\viki\Service\Models\Elequent\Client::class, \App\Policies\ClientPolicy::class);
        Gate::policy(\viki\Service\Models\Elequent\WorkPlace::class, \App\Policies\WorkPlacePolicy::class);
        Gate::policy(\viki\Service\Models\Elequent\Worker::class, \App\Policies\WorkerPolicy::class);
        Gate::policy(\viki\Service\Models\Elequent\Vacation::class, \App\Policies\VacationPolicy::class);
        Gate::policy(\viki\Service\Models\Elequent\WorkerBonus::class, \App\Policies\WorkerBonusPolicy::class);
        Gate::policy(\viki\Service\Models\Elequent\Approvement::class, \App\Policies\ApprovementPolicy::class);
        Gate::policy(\viki\Service\Models\Elequent\WorkerRecord::class, \App\Policies\WorkerRecordPolicy::class);
        Gate::policy(\viki\Service\Models\Elequent\Archive::class, \App\Policies\ArchivePolicy::class);
        Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
        Gate::policy(\Spatie\Permission\Models\Role::class, \App\Policies\RolePolicy::class);
        Gate::policy(\Spatie\Permission\Models\Permission::class, \App\Policies\PermissionPolicy::class);
        Gate::policy(\Spatie\Activitylog\Models\Activity::class, \App\Policies\ActivityPolicy::class);
    }
}
