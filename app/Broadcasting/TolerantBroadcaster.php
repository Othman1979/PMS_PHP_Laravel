<?php

namespace App\Broadcasting;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Log;
use Pusher\PusherException;

/**
 * Wraps a Pusher/Reverb broadcaster so an unreachable websocket server
 * degrades to a logged warning instead of failing the HTTP request:
 * the state change is already persisted and the UI falls back to polling.
 *
 * @mixin \Illuminate\Broadcasting\Broadcasters\Broadcaster
 */
class TolerantBroadcaster implements Broadcaster
{
    public function __construct(private readonly Broadcaster $inner) {}

    public function auth($request)
    {
        return $this->inner->auth($request);
    }

    public function validAuthenticationResponse($request, $result)
    {
        return $this->inner->validAuthenticationResponse($request, $result);
    }

    public function broadcast(array $channels, $event, array $payload = [])
    {
        try {
            $this->inner->broadcast($channels, $event, $payload);
        } catch (BroadcastException|PusherException|GuzzleException $e) {
            Log::warning('Realtime broadcast skipped: '.$e->getMessage(), ['event' => $event]);
        }
    }

    /** @param  array<int, mixed>  $parameters */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->inner->{$method}(...$parameters);
    }
}
