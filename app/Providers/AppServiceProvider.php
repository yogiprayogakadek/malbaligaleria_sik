<?php

namespace App\Providers;

use App\Contracts\MalwareScanner;
use App\Listeners\RecordApplicantEmailDelivery;
use App\Models\AnnouncementSetting;
use App\Models\DocumentRetentionSetting;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateSetting;
use App\Models\EventRequirement;
use App\Models\EventWhatsappGroupMember;
use App\Models\EventWhatsappGroupRule;
use App\Models\EventWhatsappGroupSetting;
use App\Models\LoadingPermit;
use App\Models\MailSetting;
use App\Models\MessageTemplate;
use App\Models\MonitoringSetting;
use App\Models\OperatingSchedule;
use App\Models\OperatingScheduleException;
use App\Models\PermitNotification;
use App\Models\PermitNumberSetting;
use App\Models\PermitSubmissionLimitSetting;
use App\Models\ScannerIpBlock;
use App\Models\ScannerSetting;
use App\Models\SecurityOfficer;
use App\Models\SecuritySetting;
use App\Models\SlaSetting;
use App\Models\Tenant;
use App\Models\UploadSecuritySetting;
use App\Models\User;
use App\Models\WhatsappSetting;
use App\Models\WorkPermit;
use App\Models\WorkPermitCategory;
use App\Models\WorkPermitDurationSetting;
use App\Observers\AuditableObserver;
use App\Services\ClamAvMalwareScanner;
use App\Services\MailSettingsConfigurator;
use App\Services\OperationTimeline;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MalwareScanner::class, ClamAvMalwareScanner::class);
        $this->app->singleton(OperationTimeline::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(NotificationSent::class, [RecordApplicantEmailDelivery::class, 'handleSent']);
        Event::listen(NotificationFailed::class, [RecordApplicantEmailDelivery::class, 'handleFailed']);
        Event::listen(QueryExecuted::class, fn (QueryExecuted $query) => app(OperationTimeline::class)->recordQuery($query));

        foreach ([User::class, SecurityOfficer::class, LoadingPermit::class, WorkPermit::class, EventRequirement::class, EventWhatsappGroupSetting::class, EventWhatsappGroupMember::class, EventWhatsappGroupRule::class, MailSetting::class, WhatsappSetting::class, OperatingSchedule::class, OperatingScheduleException::class, ScannerSetting::class, ScannerIpBlock::class, SecuritySetting::class, UploadSecuritySetting::class, SlaSetting::class, DocumentRetentionSetting::class, DocumentTemplate::class, DocumentTemplateSetting::class, PermitSubmissionLimitSetting::class, PermitNumberSetting::class, AnnouncementSetting::class, Tenant::class, WorkPermitCategory::class, WorkPermitDurationSetting::class, MessageTemplate::class, MonitoringSetting::class] as $model) {
            $model::observe(AuditableObserver::class);
        }

        RateLimiter::for('permit-submissions', function (Request $request): array|Limit {
            $setting = PermitSubmissionLimitSetting::current();

            if (! $setting->enabled) {
                return Limit::none();
            }

            $identity = $request->user()?->isTenant()
                ? 'tenant:'.$request->user()->getAuthIdentifier()
                : 'ip:'.hash('sha256', (string) $request->ip());
            $response = function (Request $request, array $headers) {
                $retryAfter = (int) ($headers['Retry-After'] ?? 60);
                $wait = $retryAfter >= 3600
                    ? (int) ceil($retryAfter / 3600).' jam'
                    : $retryAfter.' detik';

                return back()
                    ->withInput($request->except(['id_doc', 'assignment_letter']))
                    ->withErrors(['submission' => "Batas pengajuan tercapai. Silakan coba kembali dalam {$wait} atau hubungi admin."])
                    ->withHeaders($headers);
            };

            return [
                Limit::perMinute($setting->max_per_minute)->by("permit-submission:{$identity}:minute")->response($response),
                Limit::perDay($setting->max_per_day)->by("permit-submission:{$identity}:day")->response($response),
            ];
        });

        RateLimiter::for('form-presence', function (Request $request): Limit {
            $tabId = (string) $request->input('tab_id', 'unknown');
            $visitorId = (string) $request->input('visitor_id', 'unknown');
            $identity = hash_hmac('sha256', $visitorId.'|'.$tabId, (string) config('app.key'));

            return Limit::perMinute(12)
                ->by('form-presence:'.$identity)
                ->response(fn () => response()->json(['message' => 'Pembaruan aktivitas terlalu sering.'], 429));
        });

        RateLimiter::for('event-group-sync', function (Request $request): Limit {
            $setting = EventWhatsappGroupSetting::current();
            $maxAttempts = $setting?->group_sync_max_attempts ?? 2;
            $decaySeconds = $setting?->group_sync_decay_seconds ?? 60;

            return Limit::perSecond($maxAttempts, $decaySeconds)
                ->by('event-group-sync:'.($request->user()?->id ?? $request->ip()))
                ->response(function (Request $request, array $headers) use ($decaySeconds) {
                    $retryAfter = (int) ($headers['Retry-After'] ?? $decaySeconds);

                    return back()
                        ->withErrors(['group_sync' => "Batas sinkronisasi tercapai. Coba kembali dalam {$retryAfter} detik."])
                        ->withHeaders($headers);
                });
        });

        RateLimiter::for('event-group-outbound', function (Request $request): Limit {
            $setting = EventWhatsappGroupSetting::current();
            $maxAttempts = $setting?->outbound_max_attempts ?? 2;
            $decaySeconds = $setting?->outbound_decay_seconds ?? 60;

            return Limit::perSecond($maxAttempts, $decaySeconds)
                ->by('event-group-outbound:'.($request->user()?->id ?? $request->ip()))
                ->response(function (Request $request, array $headers) use ($decaySeconds) {
                    $retryAfter = (int) ($headers['Retry-After'] ?? $decaySeconds);

                    return back()
                        ->withErrors(['outbound_only' => "Batas perubahan mode WAHA tercapai. Coba kembali dalam {$retryAfter} detik."])
                        ->withHeaders($headers);
                });
        });

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

        RateLimiter::for('whatsapp-tests', function (Request $request): Limit {
            return Limit::perMinute(3)
                ->by('whatsapp-test:'.($request->user()?->id ?? $request->ip()))
                ->response(function (Request $request, array $headers) {
                    $retryAfter = (int) ($headers['Retry-After'] ?? 60);

                    return back()
                        ->withInput()
                        ->with('whatsapp_test_error', "Terlalu banyak percobaan. Tunggu {$retryAfter} detik sebelum mengirim pesan uji lagi.")
                        ->withHeaders($headers);
                });
        });

        RateLimiter::for('announcement-campaigns', function (Request $request): Limit {
            $setting = AnnouncementSetting::current();

            return Limit::perSecond($setting->max_attempts, $setting->decay_seconds)
                ->by('announcement-campaign:'.($request->user()?->id ?? $request->ip()))
                ->response(function (Request $request, array $headers) use ($setting) {
                    $retryAfter = (int) ($headers['Retry-After'] ?? $setting->decay_seconds);

                    return back()
                        ->with('announcement_rate_error', "Terlalu banyak percobaan pengiriman. Tunggu {$retryAfter} detik lalu coba kembali.")
                        ->withHeaders($headers);
                });
        });

        app(MailSettingsConfigurator::class)->apply();

        view()->composer('*', function ($view) {
            if (str_starts_with($view->getName(), 'errors.')) {
                return;
            }

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
                } elseif ($user->role === 'validator' && $user->division === 'EP') {
                    $pendingValidatorCount = WorkPermit::needsEpAction()->count();
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
