#!/bin/bash
A=/home/u468557257/domains/darkgreen-cattle-882066.hostingersite.com/public_html
D=$A/storage/app/pr5-deploy
mkdir -p $A/storage/app
mkdir $D 2>/dev/null || exit 0
SHA=56a491074bd01ef7fa34a7b87739d1317f722e3c
F="app/Http/Controllers/MaintenanceRequestController.php app/Http/Controllers/ReportController.php app/Models/ActivityLog.php app/Models/MaintenanceRequest.php app/Models/Setting.php app/Models/User.php app/Services/RequestWorkflow.php database/migrations/2026_11_28_000000_archive_after_one_day_and_normal_priority_without_sla.php lang/ar.json lang/en.json resources/views/requests/show.blade.php routes/web.php"
mkdir -p $D/src
curl -sSL https://codeload.github.com/Othman1979/PMS_PHP_Laravel/tar.gz/$SHA | tar xz -C $D/src --strip-components=1 || { echo DOWNLOAD-FAILED > $A/public/pr5-status.txt; exit 1; }
cd $A && tar czf $D/backup.tgz --ignore-failed-read $F
cd $D/src && cp --parents $F $A/
cd $A
{ /usr/bin/php artisan migrate --force; echo "migrate-exit=$?"; /usr/bin/php artisan optimize:clear; echo "clear-exit=$?"; } > $D/log 2>&1
rm -rf $D/src
{ cat $D/log; echo "DEPLOYED-$SHA"; } > $A/public/pr5-status.txt
sleep 300
rm -f $A/public/pr5-status.txt
