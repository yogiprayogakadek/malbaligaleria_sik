<?php

namespace App\Support;

use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use Illuminate\Support\Facades\URL;

class ApplicantStatusUrl
{
    public static function loading(LoadingPermit $permit): string
    {
        return self::temporary('loading.show', [
            'permitNumber' => $permit->permit_number,
        ]);
    }

    public static function work(WorkPermit $permit): string
    {
        return self::temporary('work-permits.status', [
            'token' => $permit->applicant_token,
        ]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private static function temporary(string $route, array $parameters): string
    {
        $expiryDays = (int) config('permit-notifications.status_link_expiry_days', 30);
        $relativeUrl = URL::temporarySignedRoute(
            $route,
            now()->addDays($expiryDays),
            $parameters,
            false,
        );

        return rtrim((string) config('app.url'), '/').'/'.ltrim($relativeUrl, '/');
    }
}
