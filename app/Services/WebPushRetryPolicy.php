<?php

namespace App\Services;

use Minishlink\WebPush\MessageSentReport;

class WebPushRetryPolicy
{
    public static function shouldRetry(MessageSentReport $report): bool
    {
        $status = $report->getResponse()?->getStatusCode();

        return ! $report->isSubscriptionExpired()
            && ($status === null || in_array($status, [408, 425, 429], true) || $status >= 500);
    }
}
