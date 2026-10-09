# CMMS — Restaurant Maintenance Management (Laravel + MySQL)

Laravel 13 / PHP 8.3 rewrite of the CMMS maintenance system, built to run on Hostinger **shared hosting** (PHP + MySQL, no VPS).

- Username login, roles: Admin, Coordinator, Technician, DepartmentManager, Employee
- Arabic (default, RTL) / English, Windows 11 Fluent-style UI, installable PWA
- QR quick request for kitchen staff (`/r/{code}`), printable equipment QR labels
- Maintenance request lifecycle with timeline, specialty-aware assignment, technician task list
- Equipment with optional warranty, preventive maintenance plans + checklists (`cmms:generate-pm`, scheduled twice daily)
- Spare parts: stock replenishment purchase requests (coordinator/admin) → admin approval → goods receipt → issue on request completion; full stock movement history
- Reports, Web Push notifications (lock-screen, iOS/Android), dashboard live polling

## Local development

```bash
cp .env.example .env            # set DB_* for your MySQL
composer install
php artisan key:generate
php artisan cmms:vapid           # paste the two keys into .env
php artisan migrate --seed      # base data + first admin (CMMS_ADMIN_USERNAME / CMMS_ADMIN_PASSWORD)
php artisan db:seed --class=DemoSeeder   # optional demo users (password 1234) and equipment
php artisan serve
php artisan test
vendor/bin/pint
```

## Deployment

See [docs/DEPLOY-HOSTINGER.md](docs/DEPLOY-HOSTINGER.md) (Arabic, step by step). `database/install/cmms-install.sql` is a ready schema + base data for installs without SSH (import with phpMyAdmin).
