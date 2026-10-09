@props(['title' => null])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ? $title.' - ' : '' }}{{ __('AppName') }}</title>
<link rel="preload" href="{{ asset('fonts/noto-kufi-arabic/NotoKufiArabic-arabic.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset(app()->getLocale() === 'ar' ? 'lib/bootstrap/dist/css/bootstrap.rtl.min.css' : 'lib/bootstrap/dist/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#f3f3f3">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="CMMS">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
