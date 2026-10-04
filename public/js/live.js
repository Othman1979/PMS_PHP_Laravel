/* Realtime layer: Laravel Echo over Reverb with graceful fallback.
 * Exposes window.PmsLive = { ready, on(channel, event, cb), onState(cb), connected, lang, L(x) }.
 * Also powers [data-live-reload="channel"] elements and the notification bell. */
(function () {
    const meta = document.querySelector('meta[name="pms-live"]');
    const cfg = meta ? JSON.parse(meta.content) : null;
    const lang = document.documentElement.lang || 'ar';
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const T = cfg?.t || {};
    const stateCbs = [];
    let connected = false;
    let echo = null;

    function L(x) { return (x && typeof x === 'object') ? (x[lang] ?? x.ar ?? x.en ?? '') : (x ?? ''); }
    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

    function setState(on) {
        if (connected === on) return;
        connected = on;
        document.documentElement.classList.toggle('live-on', on);
        stateCbs.forEach(cb => { try { cb(on); } catch (e) { console.error(e); } });
    }

    function toast(title, body, url, opts) {
        const zone = document.getElementById('toastZone');
        if (!zone || !window.bootstrap) return null;
        const el = document.createElement('div');
        el.className = 'toast';
        el.setAttribute('role', 'alert');
        el.innerHTML =
            '<div class="toast-header"><strong class="me-auto">' + esc(title) + '</strong>' +
            '<button type="button" class="btn-close" data-bs-dismiss="toast"></button></div>' +
            '<div class="toast-body">' + esc(body) +
            (url ? '<br><a href="' + esc(url) + '">' + esc(T.view || 'View') + '</a>' : '') +
            (opts && opts.action ? ' <button type="button" class="btn btn-sm btn-primary ms-2 toast-action">' + esc(opts.action) + '</button>' : '') +
            '</div>';
        zone.appendChild(el);
        if (opts && opts.onAction) el.querySelector('.toast-action')?.addEventListener('click', opts.onAction);
        const t = new bootstrap.Toast(el, { delay: opts?.delay ?? 7000, autohide: opts?.autohide !== false });
        el.addEventListener('hidden.bs.toast', () => el.remove());
        t.show();
        return el;
    }

    function connect() {
        if (!cfg || !cfg.key || !window.Echo || !window.Pusher) return;
        try {
            window.Pusher.logToConsole = false;
            echo = new window.Echo({
                broadcaster: 'reverb',
                key: cfg.key,
                wsHost: cfg.host,
                wsPort: cfg.port,
                wssPort: cfg.port,
                forceTLS: cfg.scheme === 'https',
                enabledTransports: ['ws', 'wss'],
                authEndpoint: cfg.authEndpoint,
                csrfToken: token,
            });
            const conn = echo.connector.pusher.connection;
            conn.bind('connected', () => setState(true));
            conn.bind('disconnected', () => setState(false));
            conn.bind('unavailable', () => setState(false));
            conn.bind('failed', () => setState(false));
            conn.bind('error', () => setState(false));
        } catch (e) {
            console.warn('Live: unavailable', e);
            echo = null;
        }
    }

    function on(channel, event, cb) {
        if (!echo) return false;
        echo.private(channel).listen('.' + event, payload => { try { cb(payload); } catch (e) { console.error(e); } });
        return true;
    }

    /* ---- generic "reload this page when its request changes" ---- */
    function dirtyForm() {
        return [...document.querySelectorAll('textarea, input[type=text], input[type=number], input[type=file]')]
            .some(el => (el.type === 'file' ? el.files.length > 0 : el.value.trim() !== '') && el.form && !el.form.classList.contains('live-ignore'));
    }
    function wireReloads() {
        const els = document.querySelectorAll('[data-live-reload]');
        if (!els.length) return;
        let pending = false;
        els.forEach(el => on(el.dataset.liveReload, 'request.changed', d => {
            if (pending) return;
            if (el.dataset.liveSelf && String(el.dataset.liveSelf) !== String(d.id) && el.dataset.liveSelf !== '*') return;
            pending = true;
            if (dirtyForm()) {
                toast(T.changed || 'Updated', T.updatedElsewhere || '', null, { autohide: false, action: T.refresh || 'Refresh', onAction: () => location.reload() });
            } else {
                toast(T.changed || 'Updated', T.reloading || '', null, { delay: 1500 });
                setTimeout(() => location.reload(), 1200);
            }
        }));
    }

    /* ---- notification bell ---- */
    function wireBell() {
        const bell = document.getElementById('notifBell');
        if (!bell || !cfg) return;
        const count = bell.querySelector('.notif-count');
        const list = document.getElementById('notifList');
        const readAll = document.getElementById('notifReadAll');
        let loaded = false;

        function setCount(n) {
            count.textContent = n > 99 ? '99+' : String(n);
            count.classList.toggle('d-none', !n);
            bell.classList.toggle('has-unread', n > 0);
        }
        function render(items) {
            if (!items.length) { list.innerHTML = '<div class="dropdown-item-text text-muted small">' + esc(T.none || '') + '</div>'; return; }
            list.innerHTML = items.map(n =>
                '<a class="dropdown-item notif-item' + (n.read ? '' : ' unread') + '" href="' + esc(cfg.openUrl.replace('__ID__', n.id)) + '">' +
                '<div class="fw-semibold text-wrap">' + esc(n.title) + '</div>' +
                (n.body ? '<div class="small text-muted text-wrap">' + esc(n.body) + '</div>' : '') +
                '<div class="small text-muted">' + esc(n.ago) + '</div></a>').join('');
        }
        async function load() {
            try {
                const res = await fetch(cfg.listUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!res.ok) return;
                const j = await res.json();
                setCount(j.unread);
                render(j.items);
                loaded = true;
            } catch { /* offline */ }
        }
        bell.addEventListener('show.bs.dropdown', load);
        readAll?.addEventListener('click', async e => {
            e.preventDefault();
            await fetch(cfg.readAllUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } });
            setCount(0);
            list.querySelectorAll('.unread').forEach(el => el.classList.remove('unread'));
        });
        load();
        on('user.' + cfg.userId, 'notification', n => {
            setCount((parseInt(count.textContent, 10) || 0) + 1);
            if (loaded) load();
            toast(n.title, n.body || '', n.url);
            if (navigator.vibrate) navigator.vibrate(60);
        });
        if (!echo) setInterval(() => { if (!document.hidden) load(); }, 60000);
    }

    connect();
    document.addEventListener('DOMContentLoaded', () => { wireReloads(); wireBell(); });

    window.PmsLive = {
        get connected() { return connected; },
        get enabled() { return !!echo; },
        lang, L, on, toast, esc,
        onState(cb) { stateCbs.push(cb); cb(connected); },
        userId: cfg?.userId ?? null,
    };
})();
