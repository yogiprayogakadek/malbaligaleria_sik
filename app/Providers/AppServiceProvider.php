<?php

namespace App\Providers;

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
        view()->composer('*', function ($view) {
            $notifications = collect();
            $unreadCount = 0;
            $pendingTRCount = 0;

            if (\Illuminate\Support\Facades\Auth::check()) {
                $user = \Illuminate\Support\Facades\Auth::user();
                $notifications = \App\Models\PermitNotification::where('user_id', $user->id)
                    ->latest()
                    ->take(6)
                    ->get();
                $unreadCount = \App\Models\PermitNotification::where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->count();

                if (($user->role === 'validator' && $user->division === 'TR') || $user->role === 'admin') {
                    $pendingTRCount = \App\Models\LoadingPermit::where('status', 'pending')->count();
                }
            }

            $view->with(compact('notifications', 'unreadCount', 'pendingTRCount'));
        });
    }
}
