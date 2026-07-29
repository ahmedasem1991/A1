<?php

namespace App\Providers;

use App\Models\OrderItem;
use App\Models\PaymentAllocation;
use App\Observers\OrderItemObserver;
use App\Observers\PaymentAllocationObserver;
use App\Policies\ActivityPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

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
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Deliberate global override: admins bypass every policy check, including
        // the finance policies in App\Policies. Do not assume any ability is
        // actually enforced for an is_admin user based on policy code alone.
        Gate::after(function ($user, $ability) {
            return $user->is_admin;
        });

        PaymentAllocation::observe(PaymentAllocationObserver::class);
        OrderItem::observe(OrderItemObserver::class);
    }
}
