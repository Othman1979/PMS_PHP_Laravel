(function () {
    const btn = document.getElementById('pushToggle');
    if (!('serviceWorker' in navigator)) { if (btn) btn.classList.add('d-none'); return; }

    const swReady = navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then(() => navigator.serviceWorker.ready);
    if (!btn) return;

    const t = btn.dataset;
    const label = btn.querySelector('.push-label');
    const token = document.querySelector('meta[name="csrf-token"]');
    const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const supported = 'PushManager' in window && 'Notification' in window;

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' },
            body: body ? JSON.stringify(body) : null
        });
    }

    function b64ToUint8(base64) {
        const pad = '='.repeat((4 - base64.length % 4) % 4);
        const raw = atob((base64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
    }

    function toDto(sub) {
        const j = sub.toJSON();
        return { endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth };
    }

    function setState(on) {
        btn.dataset.on = on ? '1' : '0';
        label.textContent = on ? t.labelTest : t.labelEnable;
        btn.classList.toggle('btn-warning', !on);
        btn.classList.toggle('btn-subtle', on);
        btn.title = on ? t.labelOn : t.labelEnable;
    }

    async function refresh() {
        if (!supported) { setState(false); return; }
        const reg = await swReady;
        const sub = await reg.pushManager.getSubscription();
        if (sub && Notification.permission === 'granted') {
            await post('/push/subscribe', toDto(sub));
            setState(true);
        } else {
            setState(false);
        }
    }

    async function enable() {
        if (!supported) {
            alert(isIos && !standalone ? t.msgIos : t.msgUnsupported);
            return;
        }
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') { alert(t.msgDenied); return; }
        const reg = await swReady;
        const { publicKey } = await (await fetch('/push/public-key')).json();
        let sub = await reg.pushManager.getSubscription();
        if (!sub) sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToUint8(publicKey) });
        const res = await post('/push/subscribe', toDto(sub));
        if (!res.ok) { alert(t.msgError); return; }
        setState(true);
        alert(t.msgEnabled);
    }

    btn.addEventListener('click', async () => {
        btn.disabled = true;
        try {
            if (btn.dataset.on === '1') {
                await post('/push/test');
            } else {
                await enable();
            }
        } catch (e) {
            console.error(e);
            alert(t.msgError);
        } finally {
            btn.disabled = false;
        }
    });

    btn.classList.remove('d-none');
    refresh().catch(console.error);
})();
