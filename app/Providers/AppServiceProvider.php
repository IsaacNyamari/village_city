<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        // Livewire's default full-page layout is "layouts::app"; point it at the starter kit layouts.
        \Illuminate\Support\Facades\View::addNamespace('layouts', resource_path('views/components/layouts'));

        // Role-based access is provided by spatie/laravel-permission: abilities are permission
        // names such as "farmers.manage". Super administrators bypass every check.
        Gate::before(function (User $user, string $ability) {
            if ($user->is_active === false) {
                return false;
            }

            return $user->isSuperAdmin() ? true : null;
        });

        // Orders are visible to staff (orders.view) and to customers for their own orders (orders.own).
        Gate::define('orders.access', fn (User $user) => $user->can('orders.view') || $user->can('orders.own'));

        Event::listen(function (Login $event) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            AuditLogger::log('login', $event->user, $event->user->name.' signed in');
        });
    }
}
