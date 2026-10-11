<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Restaurant identity shown across the UI, reports and PDFs. Values come from the developer settings,
 * then from CMMS_COMPANY_* environment variables, then from the translated default app name.
 */
class Branding
{
    public static function name(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $key = $locale === 'ar' ? Setting::COMPANY_NAME_AR : Setting::COMPANY_NAME_EN;
        $other = $locale === 'ar' ? Setting::COMPANY_NAME_EN : Setting::COMPANY_NAME_AR;

        $name = Setting::get($key) ?: config('cmms.company.name_'.($locale === 'ar' ? 'ar' : 'en'));
        $name = $name ?: (Setting::get($other) ?: config('cmms.company.name_'.($locale === 'ar' ? 'en' : 'ar')));

        return filled($name) ? $name : __('AppName', [], $locale);
    }

    /** Both names when they differ, e.g. "زهور الشفا — Zohor Al Shafa"; used on printed headers. */
    public static function fullName(): string
    {
        $ar = static::name('ar');
        $en = static::name('en');

        return $ar === $en ? $ar : (app()->getLocale() === 'ar' ? $ar.' — '.$en : $en.' — '.$ar);
    }

    public static function logoUrl(): ?string
    {
        $logo = Setting::get(Setting::COMPANY_LOGO) ?: config('cmms.company.logo');

        return filled($logo) ? $logo : null;
    }

    /** Absolute filesystem path of the logo for PDF embedding, or null when missing. */
    public static function logoPath(): ?string
    {
        $url = static::logoUrl();
        if ($url === null || ! str_starts_with($url, '/')) {
            return null;
        }
        $path = public_path(ltrim($url, '/'));

        return is_file($path) ? $path : null;
    }

    public static function address(): ?string
    {
        $value = Setting::get(Setting::COMPANY_ADDRESS) ?: config('cmms.company.address');

        return filled($value) ? $value : null;
    }

    public static function phone(): ?string
    {
        $value = Setting::get(Setting::COMPANY_PHONE) ?: config('cmms.company.phone');

        return filled($value) ? $value : null;
    }

    /** Address and phone on one line for report headers. */
    public static function contactLine(): ?string
    {
        $line = implode(' · ', array_filter([static::address(), static::phone()]));

        return $line !== '' ? $line : null;
    }
}
