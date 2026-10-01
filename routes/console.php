<?php

use App\Services\PmGenerator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Minishlink\WebPush\VAPID;

Artisan::command('pms:generate-pm', function (PmGenerator $generator) {
    $this->info('Generated '.$generator->generateDue().' preventive maintenance request(s).');
})->purpose('Convert due preventive maintenance plans into maintenance requests');

Artisan::command('pms:vapid {--write : Save the keys into .env instead of printing them}', function () {
    $keys = VAPID::createVapidKeys();
    $lines = ['VAPID_PUBLIC_KEY' => $keys['publicKey'], 'VAPID_PRIVATE_KEY' => $keys['privateKey']];

    if (! $this->option('write')) {
        foreach ($lines as $name => $value) {
            $this->line($name.'='.$value);
        }

        return;
    }

    $path = base_path('.env');
    $env = File::get($path);
    foreach ($lines as $name => $value) {
        $env = preg_match("/^{$name}=.*$/m", $env)
            ? preg_replace("/^{$name}=.*$/m", $name.'='.$value, $env)
            : rtrim($env).PHP_EOL.$name.'='.$value.PHP_EOL;
    }
    File::put($path, $env);
    $this->info('VAPID keys saved to .env');
})->purpose('Generate a VAPID key pair for Web Push');

Schedule::command('pms:generate-pm')->twiceDaily(6, 18)->withoutOverlapping();
