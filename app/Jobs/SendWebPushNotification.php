<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Delivers one payload to a set of browser subscriptions off the request cycle. */
class SendWebPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @param  list<int>  $subscriptionIds */
    public function __construct(public array $subscriptionIds, public string $payload) {}

    public function handle(WebPushService $push): void
    {
        $subscriptions = PushSubscription::query()->whereKey($this->subscriptionIds)->get();
        if ($subscriptions->isNotEmpty()) {
            $push->deliver($subscriptions, $this->payload);
        }
    }
}
