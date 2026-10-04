<x-layouts.quick :title="__('QuickRequest')">
    @if ($notFound)
        <div class="alert alert-danger fw-bold">{{ __('Quick_NotFound') }}: {{ $q }}</div>
    @endif

    <p class="quick-lead">{{ __('Quick_FindTitle') }}</p>

    <button type="button" class="btn btn-primary quick-scan-btn w-100 mb-3" id="scanBtn">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="7" width="4" height="4"/><rect x="13" y="7" width="4" height="4"/><rect x="7" y="13" width="4" height="4"/><path d="M13 13h4v4h-4z"/></svg>
        {{ __('Quick_ScanButton') }}
    </button>

    <div class="quick-scanner d-none" id="scanner">
        <video id="scanVideo" playsinline muted></video>
        <canvas id="scanCanvas" class="d-none"></canvas>
        <div class="quick-scanner-frame"></div>
        <div class="quick-scanner-hint">{{ __('Quick_ScanHint') }}</div>
        <button type="button" class="btn btn-light btn-sm quick-scanner-close" id="scanClose">{{ __('Cancel') }}</button>
    </div>
    <div class="alert alert-warning d-none" id="scanError">{{ __('Quick_CameraUnavailable') }}</div>

    <form method="get" action="{{ route('quick.find') }}" class="quick-search mb-3">
        <input type="search" name="q" value="{{ $q }}" class="form-control quick-text" placeholder="{{ __('Quick_FindPlaceholder') }}" autocomplete="off">
        <button class="btn btn-primary">{{ __('Search') }}</button>
    </form>

    @if ($q === '' && $myRequests->isNotEmpty())
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="quick-step-title mb-0">{{ __('Quick_MyRequests') }}</h2>
            <a href="{{ route('quick.mine') }}" class="small fw-bold">{{ __('Quick_AllMyRequests') }}</a>
        </div>
        <div class="quick-list mb-3" data-live-reload="user.{{ auth()->id() }}" data-live-self="*">
            @foreach ($myRequests as $r)
                <a class="quick-list-item" href="{{ route('requests.show', $r) }}">
                    <span class="quick-list-name">{{ $r->equipment?->name }} <small class="text-muted">{{ $r->request_number }}</small></span>
                    <span class="quick-list-meta d-flex align-items-center gap-2 flex-wrap">
                        <x-status-badge :status="$r->status" />
                        @if ($r->status === \App\Enums\RequestStatus::Completed)<strong class="text-success">{{ __('Quick_ConfirmNeeded') }}</strong>@endif
                        @if ($r->assignedTechnician)<span>{{ $r->assignedTechnician->full_name }}</span>@endif
                        <span>{{ $r->created_at->diffForHumans() }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($results->isNotEmpty())
        @if ($q === '')
            <h2 class="quick-step-title">{{ __('Quick_MyDeptEquipment') }}</h2>
        @endif
        <div class="quick-list">
            @foreach ($results as $e)
                <a class="quick-list-item" href="{{ route('quick.show', $e->code) }}">
                    <span class="quick-list-name">{{ $e->name }}</span>
                    <span class="quick-list-meta">{{ $e->code }}{{ $e->location ? ' · '.$e->location : '' }}</span>
                </a>
            @endforeach
        </div>
    @elseif ($q !== '' && ! $notFound)
        <div class="text-muted">{{ __('NoData') }}</div>
    @endif

    <x-slot:scripts>
        <script src="{{ asset('lib/jsqr/jsQR.js') }}" defer></script>
        <script>
            (function () {
                const btn = document.getElementById('scanBtn');
                const box = document.getElementById('scanner');
                const video = document.getElementById('scanVideo');
                const canvas = document.getElementById('scanCanvas');
                const err = document.getElementById('scanError');
                const base = @js(route('quick.show', 'CODE'));
                let stream = null, timer = null, done = false;

                function stop() {
                    if (timer) { cancelAnimationFrame(timer); timer = null; }
                    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
                    box.classList.add('d-none');
                }
                function found(text) {
                    if (done) return;
                    done = true;
                    stop();
                    try {
                        const u = new URL(text, location.origin);
                        if (u.origin === location.origin && /\/r\/[^/]+$/.test(u.pathname)) { location.href = u.pathname; return; }
                        const m = u.pathname.match(/\/r\/([^/]+)$/);
                        if (m) { location.href = base.replace('CODE', m[1]); return; }
                    } catch (e) { /* not a URL: treat as equipment code */ }
                    location.href = base.replace('CODE', encodeURIComponent(text.trim()));
                }
                async function start() {
                    err.classList.add('d-none');
                    done = false;
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { err.classList.remove('d-none'); return; }
                    try {
                        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                    } catch (e) { err.classList.remove('d-none'); return; }
                    box.classList.remove('d-none');
                    video.srcObject = stream;
                    await video.play();
                    const native = 'BarcodeDetector' in window ? new BarcodeDetector({ formats: ['qr_code'] }) : null;
                    const ctx = canvas.getContext('2d', { willReadFrequently: true });
                    const tick = async () => {
                        if (!stream) return;
                        if (video.readyState === video.HAVE_ENOUGH_DATA) {
                            if (native) {
                                try { const codes = await native.detect(video); if (codes.length) { found(codes[0].rawValue); return; } } catch (e) { /* fall through to jsQR */ }
                            }
                            if (window.jsQR) {
                                canvas.width = video.videoWidth; canvas.height = video.videoHeight;
                                ctx.drawImage(video, 0, 0);
                                const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                const code = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
                                if (code && code.data) { found(code.data); return; }
                            }
                        }
                        timer = requestAnimationFrame(tick);
                    };
                    timer = requestAnimationFrame(tick);
                }
                btn.addEventListener('click', start);
                document.getElementById('scanClose').addEventListener('click', stop);
                document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
            })();
        </script>
    </x-slot:scripts>
</x-layouts.quick>
