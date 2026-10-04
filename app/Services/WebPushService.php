<?php

namespace App\Services;

use App\Jobs\SendWebPushNotification;
use App\Models\PushSubscription;
use App\Models\User;
use Closure;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
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
     * Builds the title/body once per recipient language so every user reads the notification in their own language.
     * Delivery is queued; without a queue worker (QUEUE_CONNECTION=sync) it runs right after the response is sent.
     *
     * @param  iterable<int>|int  $userIds
     * @param  Closure(string $locale): array{0: string, 1: string}  $message
     */
    public function sendLocalized(iterable|int $userIds, Closure $message, ?string $url = null, ?string $tag = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $ids = is_int($userIds) ? [$userIds] : collect($userIds)->filter()->unique()->values()->all();
        if ($ids === []) {
            return;
        }

        $subscriptions = PushSubscription::query()->whereIn('user_id', $ids)->get(['id', 'user_id']);
        if ($subscriptions->isEmpty()) {
            return;
        }

        $locales = User::query()->whereKey($subscriptions->pluck('user_id')->unique())->pluck('locale', 'id');
        $fallback = config('app.locale');

        $subscriptions
            ->groupBy(fn (PushSubscription $sub) => $locales[$sub->user_id] ?? $fallback)
            ->each(function (Collection $group, string $locale) use ($message, $url, $tag) {
                [$title, $body] = $this->inLocale($locale, $message);
                $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'tag' => $tag], JSON_UNESCAPED_UNICODE);

                $job = new SendWebPushNotification($group->pluck('id')->all(), $payload);
                if (config('queue.default') === 'sync') {
                    dispatch($job)->afterResponse();
                } else {
                    dispatch($job);
                }
            });
    }

    /**
     * @param  Closure(string $locale): array{0: string, 1: string}  $message
     * @return array{0: string, 1: string}
     */
    private function inLocale(string $locale, Closure $message): array
    {
        $previous = App::getLocale();
        App::setLocale($locale);
        try {
            return $message($locale);
        } finally {
            App::setLocale($previous);
        }
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
