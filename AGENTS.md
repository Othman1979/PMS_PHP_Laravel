<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Local verification (Windows)

- `pdo_sqlite` is not enabled in php.ini, and `php artisan test` spawns a child PHP process without `-d` flags — run PHPUnit directly:

```sh
php -d extension=pdo_sqlite -d memory_limit=512M vendor/bin/phpunit tests/Feature
```

## Android build (`android/`)

- `java` is not on PATH; JDK 17 is at `C:\Users\dell\dev-tools\jdk17` and Gradle at `C:\Users\dell\dev-tools\gradle-8.9`. Dependencies resolve offline from the Gradle cache:

```sh
cd android
JAVA_HOME="C:\Users\dell\dev-tools\jdk17" C:\Users\dell\dev-tools\gradle-8.9\bin\gradle.bat :app:assembleDebug --offline
```

- `:app` (Java WebView, `com.pms.maintenance`) depends on `:design`, a Kotlin + Jetpack Compose library (`com.pms.maintenance.design`) holding the native role screens; its `MainActivity` is the app launcher and links back to the WebView `MainActivity`.
- The Kotlin compile daemon cannot connect in this environment; Gradle falls back to in-process compilation — the noisy "Could not connect to Kotlin compile daemon" stack trace is harmless.
- First build after adding a dependency needs network (drop `--offline` once so Gradle can download it).

## Native app API + realtime

- Bearer-token auth: `POST /api/login` returns `{id, name, username, role, token}`; the token is stored sha256-hashed on `users.api_token` and checked by `auth.token` middleware (`App\Http\Middleware\AuthenticateApiToken`).
- Token routes: `GET /api/tasks` (technician's assigned open tasks + recent done), `GET /api/my-requests` (employee's own), `GET|POST /api/notifications`, `POST /api/broadcasting/auth` (private-channel signature — binds the request user resolver to `Auth::user()` so `Broadcast::auth()` sees the token user).
- Live updates use plain WebSockets, **no Pusher SDK**: `Realtime.kt` opens `ws://{host}:8085/app/pms-local-key` (Reverb), waits for `pusher:connection_established`, then sends `pusher:subscribe` with the signature for `private-technician.{id}` / `private-user.{id}`. `request.changed` → refetch via API (API is source of truth); `notification` → tray notification + badge bump, deduped by `Session.lastNotifId`. Reconnects with capped exponential backoff (1s→30s), `pingInterval(25s)`, `readTimeout=0`.
- In tests `BROADCAST_CONNECTION=null`, so `channels.php` registers on the null driver — `config(['broadcasting.default' => 'reverb']); require base_path('routes/channels.php');` before asserting `Broadcast::auth` behavior.
- `withToken()` sets a persistent test header — use `withToken('bogus')` for the unauthenticated case.
