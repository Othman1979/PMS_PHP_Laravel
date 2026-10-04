<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <x-head :title="$title" />
</head>
<body class="quick-body">
    <main class="error-page">
        <div class="error-code">{{ $code }}</div>
        <h1 class="h4 mb-2">{{ $title }}</h1>
        <p class="text-muted mb-4">{{ $message }}</p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a class="btn btn-primary" href="{{ url('/') }}">{{ __('Error_BackHome') }}</a>
            <a class="btn btn-outline-secondary" href="javascript:history.back()">{{ __('Back') }}</a>
        </div>
    </main>
</body>
</html>
