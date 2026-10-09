@echo off
setlocal
cd /d "%~dp0"

set "PHP=C:\xampp\php\php.exe"
if exist "C:\php83\php.exe" set "PHP=C:\php83\php.exe"
set "MYSQL=C:\xampp\mysql\bin\mysql.exe"

if not exist "%PHP%" (
  echo PHP not found. Install XAMPP in C:\xampp or PHP 8.3 in C:\php83
  pause & exit /b 1
)

"%PHP%" -r "exit(version_compare(PHP_VERSION,'8.3.0','>=')?0:1);"
if errorlevel 1 (
  echo This system needs PHP 8.3 or newer. Your PHP is:
  "%PHP%" -v
  echo Download PHP 8.3 "VS16 x64 Thread Safe" zip from https://windows.php.net/download and extract it to C:\php83
  pause & exit /b 1
)

if not exist ".env" (
  copy ".env.xampp" ".env" >nul
  "%PHP%" artisan key:generate --force --no-interaction
  "%PHP%" artisan cmms:vapid --write --no-interaction
)

"%MYSQL%" -u root -e "CREATE DATABASE IF NOT EXISTS cmms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
if errorlevel 1 (
  echo Could not reach MySQL. Start MySQL from the XAMPP Control Panel first.
  pause & exit /b 1
)

if not exist "storage\installed.flag" (
  "%PHP%" artisan migrate --seed --force || (pause & exit /b 1)
  "%PHP%" artisan db:seed --class=DemoSeeder --force || (pause & exit /b 1)
  echo done> "storage\installed.flag"
) else (
  "%PHP%" artisan migrate --force || (pause & exit /b 1)
)
"%PHP%" artisan view:cache >nul

start "CMMS Realtime (Reverb)" /min "%PHP%" artisan reverb:start --host=0.0.0.0 --port=8085
start "CMMS Scheduler" /min "%PHP%" artisan schedule:work

echo.
echo CMMS is running on http://localhost:8000  (admin / 1234)
echo Realtime (Reverb) and the scheduler run in two minimized windows; close all three to stop.
start "" http://localhost:8000
"%PHP%" -d upload_max_filesize=12M -d post_max_size=64M -S 0.0.0.0:8000 server.php
