<?php

namespace App\Providers;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Services\MailSettingsConfigurator;
use Illuminate\Support\Facades\Auth;
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
        app(MailSettingsConfigurator::class)->apply();

        view()->composer('*', function ($view) {
            $notifications = collect();
            $unreadCount = 0;
            $pendingTRCount = 0;

            if (Auth::check()) {
                $user = Auth::user();
                $notifications = PermitNotification::where('user_id', $user->id)
                    ->latest()
                    ->take(6)
                    ->get();
                $unreadCount = PermitNotification::where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->count();

                if (($user->role === 'validator' && $user->division === 'TR') || $user->role === 'admin') {
                    $pendingTRCount = LoadingPermit::where('status', 'pending')->count();
                }
            }

            $view->with(compact('notifications', 'unreadCount', 'pendingTRCount'));
        });
    }
}
