<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\WebPush\Events\NotificationFailed;

class AppServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        //
    }


    public function boot(): void
    {

        Event::listen(NotificationFailed::class, function (NotificationFailed $event) {
            Log::warning('Web push failed', [
                'status' => $event->report->getResponse()?->getStatusCode(),
                'reason' => $event->report->getReason(),
                'expired_subscription_removed' => $event->report->isSubscriptionExpired(),
                'push_service' => parse_url($event->report->getEndpoint(), PHP_URL_HOST),
            ]);
        });
    }
}