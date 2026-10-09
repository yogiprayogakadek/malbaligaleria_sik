<?php

namespace App\Providers;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\WorkPermit;
use App\Services\MailSettingsConfigurator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('mail-tests', function (Request $request): Limit {
            return Limit::perMinute(5)
                ->by('mail-test:'.($request->user()?->id ?? $request->ip()))
                ->response(function (Request $request, array $headers) {
                    $retryAfter = (int) ($headers['Retry-After'] ?? 60);

                    return back()
                        ->withInput()
                        ->with('mail_test_error', "Terlalu banyak percobaan. Tunggu {$retryAfter} detik sebelum mengirim email uji lagi.")
                        ->withHeaders($headers);
                });
        });

        app(MailSettingsConfigurator::class)->apply();

        view()->composer('*', function ($view) {
            $notifications = collect();
            $unreadCount = 0;
            $pendingTRCount = 0;
            $pendingValidatorCount = 0;

            if (Auth::check()) {
                $user = Auth::user();
                $notifications = PermitNotification::where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->latest()
                    ->take(6)
                    ->get();
                $unreadCount = PermitNotification::where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->count();

                if (($user->role === 'validator' && $user->division === 'TR') || $user->role === 'admin') {
                    $pendingTRCount = LoadingPermit::where('status', 'pending')->count();
                }

                if ($user->isSecretary()) {
                    $pendingValidatorCount = $unreadCount;
                } elseif ($user->role === 'validator' && $user->division === 'MEP') {
                    $pendingValidatorCount = WorkPermit::needsMepAction()->count();
                } elseif ($user->role === 'validator' && $user->division === 'FIN') {
                    $pendingValidatorCount = WorkPermit::where('assigned_division', 'MEP')->where('status', 'payment_review')->count();
                } elseif ($user->role === 'validator' && $user->division === 'TR') {
                    $isTrWorkContext = request()->routeIs('tr.work-permits.*');
                    if (request()->routeIs('staff.work-permits.*') && is_string(request()->route('token'))) {
                        $isTrWorkContext = WorkPermit::where('public_token', request()->route('token'))
                            ->where('assigned_division', 'TR')
                            ->exists();
                    }

                    $pendingValidatorCount = $isTrWorkContext
                        ? WorkPermit::needsTrAction()->count()
                        : $pendingTRCount;
                }
            }

            $view->with(compact('notifications', 'unreadCount', 'pendingTRCount', 'pendingValidatorCount'));
        });
    }
}
