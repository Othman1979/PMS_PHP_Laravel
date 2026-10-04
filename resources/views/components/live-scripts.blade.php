@php
    $user = auth()->user();
    $live = config('broadcasting.default') === 'reverb' && filled(config('reverb.apps.apps.0.key'));
    $app = config('reverb.apps.apps.0.options', []);
@endphp
@if ($user)
    <meta name="pms-live" content="{{ json_encode([
        'key' => $live ? config('reverb.apps.apps.0.key') : null,
        'host' => $app['host'] ?? request()->getHost(),
        'port' => (int) ($app['port'] ?? 443),
        'scheme' => $app['scheme'] ?? 'https',
        'authEndpoint' => url('/broadcasting/auth'),
        'userId' => $user->id,
        'listUrl' => route('notifications.index'),
        'readAllUrl' => route('notifications.read-all'),
        'openUrl' => route('notifications.open', '__ID__'),
        't' => [
            'view' => __('ViewDetails'), 'changed' => __('RequestChanged'), 'reloading' => __('Live_Reloading'),
            'refresh' => __('Live_Refresh'), 'updatedElsewhere' => __('Live_UpdatedElsewhere'), 'none' => __('NoNotifications'),
        ],
    ], JSON_UNESCAPED_UNICODE) }}">
    @if ($live)
        <script src="{{ asset('lib/pusher/pusher.min.js') }}"></script>
        <script src="{{ asset('lib/echo/echo.iife.js') }}"></script>
    @endif
    <script src="{{ asset('js/live.js') }}?v={{ filemtime(public_path('js/live.js')) }}"></script>
@endif
