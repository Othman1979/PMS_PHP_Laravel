<div class="dropdown" id="notifBell">
    <button type="button" class="titlebar-btn position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="{{ __('Notifications') }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
        <span class="notif-count badge rounded-pill bg-danger d-none">0</span>
    </button>
    <div class="dropdown-menu dropdown-menu-end notif-menu">
        <div class="d-flex justify-content-between align-items-center px-3 py-2">
            <strong>{{ __('Notifications') }}</strong>
            <a href="#" id="notifReadAll" class="small">{{ __('MarkAllRead') }}</a>
        </div>
        <div class="dropdown-divider m-0"></div>
        <div id="notifList" class="notif-list"><div class="dropdown-item-text text-muted small">{{ __('NoNotifications') }}</div></div>
    </div>
</div>
