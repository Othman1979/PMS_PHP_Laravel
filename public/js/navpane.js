(function () {
    var btn = document.getElementById('paneToggle');
    var backdrop = document.getElementById('paneBackdrop');
    if (!btn) return;
    var mobile = window.matchMedia('(max-width: 991.98px)');
    if (localStorage.getItem('pms-pane-compact') === '1') document.body.classList.add('pane-compact');
    btn.addEventListener('click', function () {
        if (mobile.matches) {
            document.body.classList.toggle('pane-open');
        } else {
            var compact = document.body.classList.toggle('pane-compact');
            localStorage.setItem('pms-pane-compact', compact ? '1' : '0');
        }
    });
    if (backdrop) backdrop.addEventListener('click', function () { document.body.classList.remove('pane-open'); });
})();
