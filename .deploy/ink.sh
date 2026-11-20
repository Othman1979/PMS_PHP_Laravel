#!/bin/bash
set -e
A=/home/u468557257/domains/darkgreen-cattle-882066.hostingersite.com/public_html
D=$A/storage/app/ink-deploy
[ -f $D/done ] && { echo ALREADY-DONE; exit 0; }
mkdir -p $D/src
curl -sSL https://codeload.github.com/Othman1979/PMS_PHP_Laravel/tar.gz/78a99308f926adaf5137151e00c955ea6e75a58f | tar xz -C $D/src --strip-components=1
F="app/Http/Controllers/ManifestController.php app/Http/Controllers/SettingController.php app/Models/ActivityLog.php app/Services/DataReset.php lang/ar.json lang/en.json public/css/site.css public/js/report-grid.js resources/views/components/head.blade.php resources/views/components/reports/pdf/layout.blade.php resources/views/components/reports/pdf/table.blade.php resources/views/dashboard/index.blade.php resources/views/settings/general.blade.php routes/console.php routes/web.php "
cd $A
[ -f $D/backup.tgz ] || tar czf $D/backup.tgz --ignore-failed-read $F
cd $D/src
cp --parents $F $A/
cd $A
/usr/bin/php artisan optimize:clear
touch $D/done
rm -rf $D/src
echo DEPLOYED-78a99308f926adaf5137151e00c955ea6e75a58f
