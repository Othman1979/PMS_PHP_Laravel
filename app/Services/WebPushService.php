<?php

namespace App\Services;

use App\Events\UserNotified;
use App\Jobs\SendWebPushNotification;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Localized;
use Closure;
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

    /**
     * Sends the same text to every recipient (already in the right language).
     *
     * @param  iterable<int>|int  $userIds
     */
    public function sendToUsers(iterable|int $userIds, string $title, string $body, ?string $url = null, ?string $tag = null): void
    {
        $this->sendLocalized($userIds, fn () => [$title, $body], $url, $tag);
    }

    /**
     * Builds the title/body once per recipient language so every user reads the notification in their own language,
     * stores an in-app copy (bell icon, pushed live over WebSocket) and queues the browser push for subscribed devices.
     * Without a queue worker (QUEUE_CONNECTION=sync) the push runs right after the response is sent.
     *
     * @param  iterable<int>|int  $userIds
     * @param  Closure(string $locale): array{0: string, 1: string}  $message
     */
    public function sendLocalized(iterable|int $userIds, Closure $message, ?string $url = null, ?string $tag = null): void
    {
        $ids = is_int($userIds) ? [$userIds] : collect($userIds)->filter()->unique()->values()->all();
        if ($ids === []) {
            return;
        }

        $users = User::query()->whereKey($ids)->where('is_active', true)->get(['id', 'locale']);
        $subscriptions = $this->isConfigured()
            ? PushSubscription::query()->whereIn('user_id', $ids)->get(['id', 'user_id'])->groupBy('user_id')
            : collect();
        $fallback = config('app.locale');

        $users->groupBy(fn (User $u) => $u->locale ?: $fallback)->each(function (Collection $group, string $locale) use ($message, $url, $tag, $subscriptions) {
            [$title, $body] = Localized::in($locale, $message);

            foreach ($group as $user) {
                $notification = UserNotification::create([
                    'user_id' => $user->id, 'title' => $title, 'body' => $body, 'url' => $url, 'tag' => $tag,
                ]);
                UserNotified::dispatch($notification);
            }

            $subscriptionIds = $group->flatMap(fn (User $u) => $subscriptions->get($u->id, collect())->pluck('id'))->all();
            if ($subscriptionIds === []) {
                return;
            }

            $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'tag' => $tag], JSON_UNESCAPED_UNICODE);
            $job = new SendWebPushNotification($subscriptionIds, $payload);
            if (config('queue.default') === 'sync') {
                dispatch($job)->afterResponse();
            } else {
                dispatch($job);
            }
        });
    }

    /** @param Collection<int, PushSubscription> $subscriptions */
    public function deliver(Collection $subscriptions, string $payload): void
    {
        $this->send($subscriptions, $payload);
    }

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
