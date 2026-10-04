<?php

namespace App\Services;

use App\Models\PushSubscription;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    public function isConfigured(): bool
    {
        return filled(config('pms.vapid.public_key')) && filled(config('pms.vapid.private_key'));
    }

    public function publicKey(): ?string
    {
        return config('pms.vapid.public_key');
    }

    /** @param iterable<int>|int $userIds */
    public function sendToUsers(iterable|int $userIds, string $title, string $body, ?string $url = null, ?string $tag = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $ids = is_int($userIds) ? [$userIds] : collect($userIds)->unique()->values()->all();
        if ($ids === []) {
            return;
        }

        $subscriptions = PushSubscription::query()->whereIn('user_id', $ids)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => $tag,
        ], JSON_UNESCAPED_UNICODE);

        app()->terminating(fn () => $this->send($subscriptions, $payload));
    }

    /** Runs after the response is sent so the user never waits on the push servers.
     *
     * @param Collection<int, PushSubscription> $subscriptions */
    private function send(Collection $subscriptions, string $payload): void
    {
        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => config('pms.vapid.subject'),
                'publicKey' => config('pms.vapid.public_key'),
                'privateKey' => config('pms.vapid.private_key'),
            ]], ['TTL' => 86400, 'urgency' => 'high'], new Client(['timeout' => 10, 'connect_timeout' => 5]));

            foreach ($subscriptions as $sub) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->p256dh,
                    'authToken' => $sub->auth,
                    'contentEncoding' => 'aes128gcm',
                ]), $payload);
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::query()->where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
                } elseif (! $report->isSuccess()) {
                    Log::warning('Web push failed', ['reason' => $report->getReason()]);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Web push error', ['error' => $e->getMessage()]);
        }
    }
}
