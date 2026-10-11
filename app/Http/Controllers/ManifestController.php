<?php

namespace App\Http\Controllers;

use App\Support\Branding;
use Illuminate\Http\JsonResponse;

/** PWA manifest built at runtime so the installed app carries the configured restaurant name. */
class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $ar = Branding::name('ar');
        $en = Branding::name('en');
        $rtl = app()->getLocale() === 'ar';

        return response()->json([
            'name' => $ar === $en ? $ar : ($rtl ? $ar.' - '.$en : $en.' - '.$ar),
            'short_name' => $rtl ? $ar : $en,
            'description' => $rtl ? 'نظام إدارة صيانة المطعم' : 'Restaurant maintenance management',
            'lang' => app()->getLocale(),
            'dir' => $rtl ? 'rtl' : 'ltr',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f3f3f3',
            'theme_color' => '#f3f3f3',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=300'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
