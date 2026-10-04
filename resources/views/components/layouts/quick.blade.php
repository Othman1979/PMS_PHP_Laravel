<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <x-head :title="$title ?? null" />
</head>
<body class="quick-body">
    <header class="quick-bar">
        <a class="quick-brand" href="{{ route('quick.find') }}">
            <span class="brand-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            </span>
            {{ __('QuickRequest') }}
        </a>
        <div class="d-flex align-items-center gap-1">
            <x-culture-switcher />
            @auth
                    <a class="quick-home" href="{{ auth()->user()->isEmployee() ? route('quick.mine') : route('requests.index') }}" title="{{ __('MyRequests') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                        <span class="quick-home-label">{{ __('MyRequests') }}</span>
                    </a>
                <form action="{{ route('logout') }}" method="post" class="d-flex">
                    @csrf
                    <button type="submit" class="quick-home" title="{{ __('Logout') }}" aria-label="{{ __('Logout') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                    </button>
                </form>
            @endauth
        </div>
    </header>
    <main class="quick-main">
        <x-flash />
        {{ $slot }}
    </main>
    <div id="toastZone" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
    <script src="{{ asset('lib/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/image-resize.js') }}?v={{ filemtime(public_path('js/image-resize.js')) }}"></script>
    <x-live-scripts />
    {{ $scripts ?? '' }}
</body>
</html>
