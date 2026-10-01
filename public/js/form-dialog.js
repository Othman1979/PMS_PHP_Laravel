/* Opens add/edit forms as a centered window over the current page. */
(function () {
    var FORM_PATH = /\/(create|edit|receive|password)\/?$/;
    var script = document.currentScript;
    var closeLabel = (script && script.dataset.closeLabel) || 'Close';

    function toUrl(href) {
        try { return new URL(href, window.location.href); } catch (e) { return null; }
    }

    function isFormUrl(url) {
        return url && url.origin === window.location.origin && FORM_PATH.test(url.pathname);
    }

    function withDialogFlag(url) {
        var u = new URL(url.href);
        u.searchParams.set('dialog', '1');
        return u.href;
    }

    function plainClick(e, link) {
        return e.button === 0 && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey
            && !link.target && !link.hasAttribute('download') && !link.hasAttribute('data-no-dialog');
    }

    if (document.body.classList.contains('in-dialog')) {
        var host = window.parent !== window ? window.parent.PmsFormDialog : null;
        if (!host) {
            var direct = new URL(window.location.href);
            direct.searchParams.delete('dialog');
            window.location.replace(direct.href);
            return;
        }

        document.querySelectorAll('form').forEach(function (form) {
            if (form.querySelector('input[name="_dialog"]')) return;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_dialog';
            input.value = '1';
            form.appendChild(input);
        });

        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[href]');
            if (!link || !plainClick(e, link)) return;
            var url = toUrl(link.getAttribute('href'));
            if (!url || url.origin !== window.location.origin || link.getAttribute('href').charAt(0) === '#') return;
            e.preventDefault();
            if (isFormUrl(url)) {
                window.location.href = withDialogFlag(url);
            } else if (link.closest('form')) {
                host.close();
            } else {
                host.navigate(url.href);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !document.querySelector('.dropdown-menu.show')) host.close();
        });
        return;
    }

    var overlay = null, frame = null, titleEl = null, loads = 0, resizeObserver = null, lastFocus = null;

    function build() {
        overlay = document.createElement('div');
        overlay.className = 'fdialog-backdrop';
        overlay.innerHTML =
            '<div class="fdialog" role="dialog" aria-modal="true" aria-labelledby="fdialogTitle">' +
            '<div class="fdialog-titlebar"><span class="fdialog-title" id="fdialogTitle"></span>' +
            '<button type="button" class="fdialog-close"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 5l14 14M19 5L5 19"/></svg></button></div>' +
            '<div class="fdialog-body"><div class="fdialog-loading"><span class="spinner-border spinner-border-sm"></span></div>' +
            '<iframe class="fdialog-frame" title=""></iframe></div></div>';
        var closeBtn = overlay.querySelector('.fdialog-close');
        closeBtn.setAttribute('aria-label', closeLabel);
        closeBtn.title = closeLabel;
        closeBtn.addEventListener('click', close);
        titleEl = overlay.querySelector('.fdialog-title');
        frame = overlay.querySelector('.fdialog-frame');
        frame.addEventListener('load', onFrameLoad);
        document.body.appendChild(overlay);
    }

    function fit() {
        if (!frame || !frame.contentDocument || !frame.contentDocument.body) return;
        frame.style.height = frame.contentDocument.documentElement.scrollHeight + 'px';
    }

    function onFrameLoad() {
        if (!overlay) return;
        var doc;
        try { doc = frame.contentDocument; } catch (e) { doc = null; }
        if (!doc || !doc.body || doc.URL === 'about:blank') return;
        if (doc.body.hasAttribute('data-dialog-close')) return;
        if (!doc.body.classList.contains('in-dialog')) {
            navigate(frame.contentWindow.location.href);
            return;
        }
        loads++;
        overlay.querySelector('.fdialog').setAttribute('data-size', doc.body.getAttribute('data-dialog-size') || 'md');
        overlay.classList.remove('is-loading');
        var heading = doc.querySelector('.dialog-main h1, .dialog-main h2');
        var title = heading ? heading.textContent.trim() : doc.title;
        if (heading) {
            var wrap = heading.parentElement;
            heading.classList.add('d-none');
            if (wrap && wrap !== doc.querySelector('.dialog-main') && wrap.children.length === 1) wrap.classList.add('d-none');
        }
        titleEl.textContent = title;
        frame.title = title;
        if (resizeObserver) resizeObserver.disconnect();
        if (window.ResizeObserver) {
            resizeObserver = new ResizeObserver(fit);
            resizeObserver.observe(doc.body);
        }
        fit();
        var invalid = doc.querySelector('.is-invalid, .alert-danger');
        var first = doc.querySelector('.dialog-main input:not([type=hidden]):not([disabled]), .dialog-main select, .dialog-main textarea');
        if (invalid && invalid.scrollIntoView) invalid.scrollIntoView({ block: 'nearest' });
        if (first && window.matchMedia('(pointer: fine)').matches) first.focus({ preventScroll: true });
    }

    function open(url) {
        if (!overlay) build();
        lastFocus = document.activeElement;
        loads = 0;
        titleEl.textContent = '';
        frame.style.height = '';
        overlay.classList.add('is-loading');
        overlay.querySelector('.fdialog').setAttribute('data-size', 'md');
        document.body.classList.remove('pane-open');
        document.body.classList.add('fdialog-open');
        frame.src = withDialogFlag(url);
        requestAnimationFrame(function () { overlay.classList.add('show'); });
    }

    function teardown() {
        if (resizeObserver) { resizeObserver.disconnect(); resizeObserver = null; }
        if (overlay) overlay.remove();
        overlay = null; frame = null; titleEl = null;
        document.body.classList.remove('fdialog-open');
    }

    function close() {
        if (!overlay) return;
        var changed = loads > 1;
        teardown();
        if (changed) {
            window.location.reload();
        } else if (lastFocus && lastFocus.focus) {
            lastFocus.focus();
        }
    }

    function navigate(href) {
        teardown();
        window.location.href = href;
    }

    window.PmsFormDialog = { open: open, close: close, navigate: navigate };

    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[href]');
        if (!link || !plainClick(e, link)) return;
        var url = toUrl(link.getAttribute('href'));
        if (!isFormUrl(url)) return;
        e.preventDefault();
        open(url);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay) close();
    });
})();
