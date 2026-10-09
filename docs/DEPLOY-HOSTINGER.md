# تشغيل نظام CMMS على استضافة Hostinger العادية (Shared Hosting)

النظام مكتوب بـ Laravel (PHP) مع MySQL، فبيشتغل على أي خطة استضافة عادية عند Hostinger بدون VPS.

## المتطلبات

- خطة استضافة Hostinger (Premium أو Business أو أعلى).
- دومين أو دومين فرعي، مثلاً `cmms.triple7foodmasters.com`. (الأمثلة تحت بدومين `triple7foodmasters.com`: الأفضل تعمل دومين فرعي `cmms.triple7foodmasters.com` وتخلّي الموقع الرئيسي متل ما هو.)
- ملف `CMMS-Laravel-hostinger.zip` (فيه النظام كامل مع مجلد `vendor`، فما بتحتاج Composer على السيرفر).

## 1. إعداد PHP والدومين

1. من hPanel افتح **Websites ← Manage**.
2. من **Advanced ← PHP Configuration** اختار **PHP 8.3** أو أحدث، وتأكد إنه الإضافات `pdo_mysql` و`mbstring` و`openssl` و`gmp` و`gd` و`zip` و`xml` مفعّلة (`zip` و`gd` لازمين لتصدير Excel وPDF وقالب استيراد المعدات).
3. إذا بدك دومين فرعي: **Domains ← Subdomains** واعمل `cmms`.
4. فعّل **SSL** المجاني للدومين من **Security ← SSL**. لازم يكون الموقع على `https`، لأنه إشعارات التلفون وتثبيت التطبيق ما بيشتغلوا بدونه.

## 2. قاعدة البيانات

1. من **Databases ← Management** اعمل قاعدة بيانات جديدة ومستخدم إلها.
2. احفظ الاسم الكامل للقاعدة واسم المستخدم (بيبلشوا بـ `u123456789_`) وكلمة السر.

## 3. رفع الملفات

في طريقتين. الأولى أفضل إذا عندك SSH.

### الطريقة أ: مع SSH (مفضّلة)

1. فعّل SSH من **Advanced ← SSH Access**، وادخل من PowerShell:
   ```
   ssh -p 65002 u123456789@IP-السيرفر
   ```
2. ارفع الملف `CMMS-Laravel-hostinger.zip` على مجلد الدومين من **File Manager**، مثلاً `domains/cmms.triple7foodmasters.com/`.
3. فك الضغط وخلّي `public_html` يأشّر على مجلد `public` تبع النظام:
   ```
   cd ~/domains/cmms.triple7foodmasters.com
   unzip CMMS-Laravel-hostinger.zip -d cmms
   mv public_html public_html_old
   ln -s cmms/public public_html
   cd cmms
   cp .env.example .env
   ```

### الطريقة ب: بدون SSH (File Manager بس)

1. افتح **File Manager** وادخل على `public_html` تبع الدومين، وامسح الملف الافتراضي `default.php` إذا موجود.
2. ارفع `CMMS-Laravel-hostinger.zip` جوّا `public_html` واعمله **Extract** هناك.
3. الملف `.htaccess` اللي برّا (بمجلد `public_html`) بيحوّل كل الطلبات لمجلد `public`، فملفات النظام و`.env` ما بتنفتح من المتصفح.
4. انسخ `.env.example` باسم `.env`.

## 4. ملف الإعدادات `.env`

افتح `.env` وعدّل هدول الأسطر:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cmms.triple7foodmasters.com
CMMS_PUBLIC_URL=https://cmms.triple7foodmasters.com

DB_HOST=localhost
DB_DATABASE=u123456789_cmms
DB_USERNAME=u123456789_cmms
DB_PASSWORD=كلمة-سر-القاعدة

CMMS_ADMIN_USERNAME=admin
CMMS_ADMIN_PASSWORD=كلمة-سر-قوية-للمدير

VAPID_SUBJECT=mailto:your-email@gmail.com
```

- `CMMS_PUBLIC_URL` هو الرابط اللي بينطبع بملصقات الـ QR. حطّه قبل ما تطبع الملصقات.
- `VAPID_SUBJECT` لازم يكون إيميل حقيقي، لأنه Apple بترفض الإشعارات إذا كان وهمي.

## 5. التنصيب

### مع SSH

```
cd ~/domains/cmms.triple7foodmasters.com/cmms
php artisan key:generate
php artisan cmms:vapid --write
php artisan migrate --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### بدون SSH

1. من **Databases ← phpMyAdmin** افتح القاعدة، واختار **Import**، وارفع الملف `database/install/cmms-install.sql`.
2. بهاي الطريقة بينعمل المدير الأول باسم `admin` وكلمة سر `ChangeMe!2026`. غيّرها أول ما تدخل من **تغيير كلمة المرور**.
3. `APP_KEY` ومفاتيح الإشعارات بينعملوا بأمر `php artisan`. بدون SSH بتشغّله مرة وحدة من **Advanced ← Cron Jobs**: ضيف مهمة كل دقيقة (`* * * * *`) فيها:
   ```
   cd /home/u123456789/domains/cmms.triple7foodmasters.com/public_html && /usr/bin/php artisan key:generate --force && /usr/bin/php artisan cmms:vapid --write
   ```
   استنى دقيقتين، وتأكد من File Manager إنه `APP_KEY` و`VAPID_PUBLIC_KEY` صار فيهم قيم بملف `.env`، وبعدين **امسح هاي المهمة فوراً**. إذا ضلّت، كل دقيقة بيتغيّر المفتاح وبيطلع كل المستخدمين من حساباتهم.

## 6. المهام المجدولة (Cron)

من **Advanced ← Cron Jobs** ضيف مهمة كل دقيقة (`* * * * *`):

```
/usr/bin/php /home/u123456789/domains/cmms.triple7foodmasters.com/cmms/artisan schedule:run
```

(بطريقة File Manager المسار بيكون `.../public_html/artisan`.) هاي المهمة بتعمل طلبات الصيانة الوقائية لحالها مرتين باليوم.

> مسار PHP والمجلدات ممكن يختلف شوي على حسابك. بتلاقي المسار الصح بصفحة Cron Jobs نفسها أو بأمر `which php` من SSH.

## 7. أول دخول

1. افتح `https://cmms.triple7foodmasters.com` وادخل بحساب المدير.
2. من **المستخدمين** ضيف المنسّق والفنيين (مع الاختصاص) والموظفين.
3. من **الأقسام** و**المعدات** دخّل بيانات المطعم، وبعدين اطبع ملصقات الـ QR من قائمة المعدات.
4. على كل تلفون: افتح الرابط، وضيفه على الشاشة الرئيسية (بالآيفون من Safari ← مشاركة ← إضافة إلى الشاشة الرئيسية)، وادخل، واضغط **تفعيل الإشعارات**.

## التحديث لنسخة جديدة

1. خذ نسخة احتياطية من القاعدة (phpMyAdmin ← Export) ومن مجلد `public/uploads`.
2. ارفع الملفات الجديدة فوق القديمة بدون ما تمسح `.env` ولا `public/uploads`.
3. مع SSH:
   ```
   php artisan migrate --force
   php artisan optimize:clear
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```

## مشاكل شائعة

| المشكلة | الحل |
|---|---|
| صفحة بيضاء أو خطأ 500 | تأكد من `.env` ومن صلاحيات الكتابة على `storage` و`bootstrap/cache` (755). شوف `storage/logs/laravel.log`. |
| ملصقات QR بتفتح رابط غلط | عدّل `CMMS_PUBLIC_URL` وشغّل `php artisan config:cache`، وبعدين اطبع الملصقات من جديد. |
| الإشعارات ما بتوصل للآيفون | لازم الموقع يكون `https`، والتطبيق مضاف على الشاشة الرئيسية، و`VAPID_SUBJECT` إيميل حقيقي. |
| صورة كبيرة بتنرفض ("فشل رفع الصورة") | الملف `public/.user.ini` بيرفع الحد لـ 12MB. إذا ما زبط، من **Advanced ← PHP Configuration ← PHP Options** خلّي `upload_max_filesize` = 12M و`post_max_size` = 64M. التلفون كمان بيصغّر الصور لحاله قبل ما يرفعها. |
| الصور المرفوعة ما بتطلع | الصور بتنحفظ بـ `public/uploads`. تأكد إنه المجلد موجود وقابل للكتابة. |
| تصدير PDF بيعطي خطأ 500 | مجلد `storage/app/mpdf` لازم يكون قابل للكتابة (mPDF بيخزّن فيه ملفات الخط المؤقتة). |
