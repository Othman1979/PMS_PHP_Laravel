<?php

return [
    // Absolute base URL encoded in equipment QR labels; falls back to the current request URL.
    'public_url' => env('CMMS_PUBLIC_URL'),

    // First admin account created by `php artisan db:seed` on a fresh install.
    'admin_username' => env('CMMS_ADMIN_USERNAME', 'admin'),
    'admin_password' => env('CMMS_ADMIN_PASSWORD', 'ChangeMe!2026'),

    // Restaurant identity defaults; the developer settings page overrides these at runtime.
    'company' => [
        'name_ar' => env('CMMS_COMPANY_NAME_AR'),
        'name_en' => env('CMMS_COMPANY_NAME_EN'),
        'logo' => env('CMMS_COMPANY_LOGO'),
        'address' => env('CMMS_COMPANY_ADDRESS'),
        'phone' => env('CMMS_COMPANY_PHONE'),
    ],

    // Upload limits (kilobytes) and allowed extensions.
    'upload_max_kb' => 10240,
    'upload_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'],

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],
];
