<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Broadcaster;

class TolerantBroadcastManager extends BroadcastManager
{
    protected function createReverbDriver(array $config): Broadcaster
    {
        return new TolerantBroadcaster(parent::createReverbDriver($config));
    }

    protected function createPusherDriver(array $config): Broadcaster
    {
        return new TolerantBroadcaster(parent::createPusherDriver($config));
    }
}
