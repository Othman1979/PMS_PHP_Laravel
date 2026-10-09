<?php

namespace App\Providers;

use App\Broadcasting\TolerantBroadcastManager;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->extend(BroadcastManager::class, fn (BroadcastManager $manager, $app) => new TolerantBroadcastManager($app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
