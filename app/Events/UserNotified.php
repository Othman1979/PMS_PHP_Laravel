<?php

namespace App\Events;

use App\Models\UserNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Pushes a new in-app notification to the recipient's open tabs (bell counter + toast). */
class UserNotified implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public UserNotification $notification) {}

    public function broadcastAs(): string
    {
        return 'notification';
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->notification->user_id);
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->notification->toLive();
    }
}
