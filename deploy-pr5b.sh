#!/bin/bash
A=/home/u468557257/domains/darkgreen-cattle-882066.hostingersite.com/public_html
D=$A/storage/app/pr5b-deploy
mkdir -p $A/storage/app
mkdir $D 2>/dev/null || exit 0
SHA=36d7071c6fce26dfeeb14e813bc0a5fb39c8e3c6
F="app/Http/Controllers/MaintenanceRequestController.php app/Http/Controllers/ReportController.php app/Models/User.php app/Services/RequestWorkflow.php database/migrations/2026_11_28_000000_archive_after_one_day_and_normal_priority_without_sla.php database/migrations/2026_11_28_000001_restore_permanent_repair_deadlines_on_follow_ups.php lang/ar.json lang/en.json"
mkdir -p $D/src
curl -sSL https://codeload.github.com/Othman1979/PMS_PHP_Laravel/tar.gz/$SHA | tar xz -C $D/src --strip-components=1 || { echo DOWNLOAD-FAILED > $A/public/pr5b-status.txt; exit 1; }
cd $A && tar czf $D/backup.tgz --ignore-failed-read $F
cd $D/src && cp --parents $F $A/
cd $A
{ /usr/bin/php artisan migrate --force; echo "migrate-exit=$?"; /usr/bin/php artisan optimize:clear; echo "clear-exit=$?"; } > $D/log 2>&1
rm -rf $D/src
{ cat $D/log; echo "DEPLOYED-$SHA"; } > $A/public/pr5b-status.txt
sleep 300
rm -f $A/public/pr5b-status.txt
