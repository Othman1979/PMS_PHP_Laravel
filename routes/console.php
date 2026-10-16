<?php

use App\Enums\Role;
use App\Models\MaintenanceRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\CalibrationReminder;
use App\Services\DatabaseBackup;
use App\Services\PmGenerator;
use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Minishlink\WebPush\VAPID;

Artisan::command('cmms:generate-pm', function (PmGenerator $generator) {
    $this->info('Generated '.$generator->generateDue().' preventive maintenance request(s).');
})->purpose('Convert due preventive maintenance plans into maintenance requests');

Artisan::command('cmms:vapid {--write : Save the keys into .env instead of printing them}', function () {
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

Schedule::command('cmms:generate-pm')->twiceDaily(6, 18)->withoutOverlapping();

Artisan::command('cmms:escalate-overdue', function (RequestWorkflow $workflow) {
    if (! Setting::bool(Setting::ESCALATE_OVERDUE, true)) {
        $this->info('Escalation disabled in settings.');

        return;
    }
    $this->info('Escalated '.$workflow->escalateOverdue().' overdue request(s).');
})->purpose('Alert staff and technicians about open requests past their SLA');

Schedule::command('cmms:escalate-overdue')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('cmms:backup', function (DatabaseBackup $backup) {
    $dir = storage_path('app/backups');
    $path = $backup->toFile($dir);
    $pruned = $backup->prune($dir, max(1, (int) Setting::get(Setting::BACKUP_KEEP, '14')));
    $this->info("Backup written to {$path} ({$pruned} old file(s) removed).");
})->purpose('Write a SQL backup to storage/app/backups and prune old ones');

Schedule::command('cmms:backup')->dailyAt('02:30')->withoutOverlapping();

Artisan::command('cmms:calibration-reminders', function (CalibrationReminder $reminder) {
    $this->info('Notified about '.$reminder->send().' measuring device(s) with calibration due or expired.');
})->purpose('Alert maintenance staff and food-safety officers about calibration due soon / expired');

Schedule::command('cmms:calibration-reminders')->dailyAt('07:00')->withoutOverlapping();

Artisan::command('cmms:developer {username} {--name=} {--password=}', function () {
    $username = trim($this->argument('username'));
    $user = User::query()->where('username', $username)->first();
    $password = $this->option('password') ?: ($user === null ? $this->secret('Password for the new developer account') : null);

    if ($user === null && blank($password)) {
        $this->error('A password is required to create a new developer account.');

        return self::FAILURE;
    }

    $user ??= new User(['username' => $username, 'full_name' => $this->option('name') ?: 'Developer']);
    $user->forceFill(['role' => Role::Admin, 'is_active' => true, 'is_developer' => true]);
    if ($this->option('name')) {
        $user->full_name = $this->option('name');
    }
    if (filled($password)) {
        $user->password = $password;
    }
    $user->save();

    $this->info("Developer account '{$username}' is ready (hidden from the user list, admin rights + /dev/settings).");

    return self::SUCCESS;
})->purpose('Create or promote a hidden developer account that can edit branding and deployment settings');

Artisan::command('cmms:archive-requests', function () {
    $days = Setting::archiveDays();
    $count = MaintenanceRequest::query()->archivable($days)->update(['archived_at' => now()]);
    $this->info("Archived {$count} request(s) finished more than {$days} days ago.");
})->purpose('Archive completed, closed and cancelled requests older than the retention period');
Schedule::command('cmms:archive-requests')->dailyAt('03:00')->withoutOverlapping();
