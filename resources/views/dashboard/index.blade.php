<x-layouts.app :title="__('Dashboard')">
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="d-flex align-items-center gap-2 mb-0">{{ __('Dashboard') }}
        <span id="liveBadge" class="badge rounded-pill bg-secondary fs-6 fw-normal" >
            <span class="live-dot"></span> <span id="liveText">{{ __('Offline') }}</span>
        </span>
    </h2>
    <a class="btn btn-primary" href="{{ route('requests.create') }}">+ {{ __('NewRequest') }}</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <a class="text-decoration-none text-reset" href="{{ route('requests.index') }}">
            <div class="stat-card">
                <span class="stat-icon icon-blue"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6M9 16h6"/></svg></span>
                <span><span class="stat-value d-block" id="stat-open">{{ $stats['open'] }}</span><span class="stat-label">{{ __('OpenRequests') }}</span></span>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a class="text-decoration-none text-reset" href="{{ route('requests.index', ['status' => 'InProgress']) }}">
            <div class="stat-card">
                <span class="stat-icon icon-amber"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
                <span><span class="stat-value d-block" id="stat-progress">{{ $stats['inProgress'] }}</span><span class="stat-label">{{ __('InProgressRequests') }}</span></span>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a class="text-decoration-none text-reset" href="{{ route('requests.index', ['status' => 'WaitingParts']) }}">
            <div class="stat-card">
                <span class="stat-icon icon-red"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/></svg></span>
                <span><span class="stat-value d-block" id="stat-parts">{{ $stats['waitingParts'] }}</span><span class="stat-label">{{ __('WaitingParts') }}</span></span>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a class="text-decoration-none text-reset" href="{{ route('requests.index', ['status' => 'Closed']) }}">
            <div class="stat-card">
                <span class="stat-icon icon-green"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg></span>
                <span><span class="stat-value d-block" id="stat-done">{{ $stats['completedThisMonth'] }}</span><span class="stat-label">{{ __('CompletedThisMonth') }}</span></span>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a class="text-decoration-none text-reset" href="{{ route('equipment.index') }}">
            <div class="stat-card">
                <span class="stat-icon icon-cyan"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 15h3M1 9h3M1 15h3"/></svg></span>
                <span><span class="stat-value d-block" id="stat-equip">{{ $stats['totalEquipment'] }}</span><span class="stat-label">{{ __('TotalEquipment') }}</span></span>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a class="text-decoration-none text-reset" href="{{ route('equipment.index') }}">
            <div class="stat-card">
                <span class="stat-icon icon-slate"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m10.29 3.86-8.2 14.14A2 2 0 0 0 3.82 21h16.36a2 2 0 0 0 1.73-3l-8.2-14.14a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg></span>
                <span><span class="stat-value d-block text-danger" id="stat-down">{{ $stats['downEquipment'] }}</span><span class="stat-label">{{ __('DownEquipment') }}</span></span>
            </div>
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><strong>{{ __('Trend30Days') }}</strong></div>
            <div class="card-body"><div class="chart-box"><canvas id="chartTrend"></canvas></div></div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card h-100">
            <div class="card-header"><strong>{{ __('RequestsByStatus') }}</strong></div>
            <div class="card-body"><div class="chart-box"><canvas id="chartStatus"></canvas></div></div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-header"><strong>{{ __('AgingOpenRequests') }}</strong> <small class="text-muted">· {{ __('OpenByDepartment') }}</small></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6"><div class="chart-box"><canvas id="chartAging"></canvas></div></div>
                    <div class="col-6"><div class="chart-box"><canvas id="chartDept"></canvas></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <strong>{{ __('RecentRequests') }}</strong>
                <a href="{{ route('requests.index') }}">{{ __('ViewAll') }}</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('RequestNumber') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Equipment') }}</th>
                            <th>{{ __('Department') }}</th>
                            <th>{{ __('Technician') }}</th>
                            <th>{{ __('Priority') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('CreatedAt') }}</th>
                        </tr>
                    </thead>
                    <tbody id="recentRows">
                        @forelse ($recent as $r)
                            <tr id="req-{{ $r->id }}">
                                <td><a href="{{ route('requests.show', $r) }}">{{ $r->request_number }}</a></td>
                                <td class="text-truncate" style="max-width:220px">{{ $r->description }}</td>
                                <td class="c-equip">{{ $r->equipment?->name ?? '-' }}</td>
                                <td class="c-dept">{{ $r->department?->localized_name }}</td>
                                <td class="c-tech">{{ $r->assignedTechnician?->full_name ?? '-' }}</td>
                                <td><x-status-badge :status="$r->priority" class="prio-badge" /></td>
                                <td><x-status-badge :status="$r->status" class="status-badge" /></td>
                                <td class="c-date">{{ $r->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr class="empty-row"><td colspan="8" class="text-center text-muted">{{ __('NoData') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div id="criticalCard" class="card border-danger {{ $critical->isEmpty() ? 'd-none' : '' }}">
            <div class="card-header bg-danger text-white"><strong>{{ __('CriticalRequests') }}</strong></div>
            <ul id="criticalList" class="list-group list-group-flush">
                @foreach ($critical as $r)
                    <li id="crit-{{ $r->id }}" class="list-group-item d-flex justify-content-between">
                        <span><a href="{{ route('requests.show', $r) }}">{{ $r->request_number }}</a> — <span class="c-desc">{{ $r->description }}</span></span>
                        <x-status-badge :status="$r->status" class="status-badge" />
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><strong>{{ __('RequestsByStatus') }}</strong></div>
            <ul class="list-group list-group-flush">
                @foreach (\App\Enums\RequestStatus::cases() as $s)
                    <li class="list-group-item d-flex justify-content-between">
                        <x-status-badge :status="$s" />
                        <span class="fw-semibold" data-status-count="{{ $s->value }}">{{ $stats['byStatus'][$s->value] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card mb-4">
            <div class="card-header"><strong>{{ __('WarrantyExpiring') }}</strong></div>
            <ul class="list-group list-group-flush">
                @forelse ($warrantyExpiring as $e)
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="{{ route('equipment.show', $e) }}">{{ $e->name }}</a>
                        <span class="text-danger">{{ $e->warranty_end?->format('Y-m-d') }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">{{ __('NoData') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header"><strong>{{ __('UpcomingPM') }}</strong></div>
            <ul class="list-group list-group-flush">
                @forelse ($pmDueSoon as $p)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $p->equipment?->name }} — {{ $p->localized_task }}</span>
                        <span class="badge {{ $p->isOverdue() ? 'bg-danger' : 'bg-info text-dark' }}">{{ $p->next_due_date->format('Y-m-d') }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">{{ __('NoData') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

<x-slot:scripts>
<script src="{{ asset('lib/chartjs/chart.umd.js') }}"></script>
<script>
(function () {
    if (!window.Chart) return;
    const C = @js($charts);
    const rtl = document.documentElement.dir === 'rtl';
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    const palette = ['#0d6efd', '#6c757d', '#6610f2', '#20c997', '#fd7e14', '#dc3545', '#198754', '#ffc107', '#343a40', '#adb5bd'];
    const statusColors = { New: '#0d6efd', UnderReview: '#6c757d', Assigned: '#6610f2', Accepted: '#20c997', InProgress: '#fd7e14', WaitingParts: '#dc3545', Completed: '#198754', Reopened: '#ffc107', Closed: '#343a40', Cancelled: '#adb5bd' };
    const T = @js(['created' => __('Created'), 'completed' => __('Completed'), 'open' => __('OpenRequests')]);

    new Chart(document.getElementById('chartTrend'), {
        type: 'line',
        data: { labels: C.trend.labels, datasets: [
            { label: T.created, data: C.trend.created, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.12)', fill: true, tension: .3, pointRadius: 2 },
            { label: T.completed, data: C.trend.completed, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.12)', fill: true, tension: .3, pointRadius: 2 },
        ] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', rtl } }, scales: { x: { reverse: rtl, ticks: { maxTicksLimit: 8 } }, y: { beginAtZero: true, ticks: { precision: 0 }, position: rtl ? 'right' : 'left' } } }
    });

    const statusChart = new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: { labels: C.status.labels, datasets: [{ data: C.status.keys.map(() => 0), backgroundColor: C.status.keys.map(k => statusColors[k] || '#999') }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { display: false } } }
    });
    window.PmsCharts = {
        status(byStatus) { statusChart.data.datasets[0].data = C.status.keys.map(k => byStatus[k] || 0); statusChart.update('none'); }
    };
    window.PmsCharts.status(Object.fromEntries(Array.from(document.querySelectorAll('[data-status-count]')).map(el => [el.dataset.statusCount, parseInt(el.textContent, 10) || 0])));

    new Chart(document.getElementById('chartAging'), {
        type: 'bar',
        data: { labels: C.aging.labels, datasets: [{ label: T.open, data: C.aging.values, backgroundColor: ['#198754', '#0d6efd', '#fd7e14', '#dc3545'] }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { reverse: rtl }, y: { beginAtZero: true, ticks: { precision: 0 }, position: rtl ? 'right' : 'left' } } }
    });
    new Chart(document.getElementById('chartDept'), {
        type: 'bar',
        data: { labels: C.departments.labels, datasets: [{ label: T.open, data: C.departments.values, backgroundColor: palette }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 }, reverse: rtl }, y: { position: rtl ? 'right' : 'left' } } }
    });
})();
</script>
<script>
(function () {
    const T = @js(['newRequest' => __('NewRequest'), 'updated' => __('RequestUpdated'), 'view' => __('ViewDetails'), 'live' => __('Live'), 'offline' => __('Offline')]);
    const STATS_URL = @js(route('dashboard.stats'));
    const RECENT_MAX = 8;
    const POLL_SLOW = 60000, POLL_FAST = 15000;
    const CHANNEL = @js(auth()->user()->canManage() ? 'staff' : 'department.'.auth()->user()->department_id);
    const live = window.PmsLive;
    const L = live ? live.L : (x => (x && typeof x === 'object') ? (x[document.documentElement.lang] ?? '') : (x ?? ''));
    let since = @js($now);
    let pollTimer = null;

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function showToast(title, d) {
        const zone = document.getElementById('toastZone');
        if (!zone) return;
        const el = document.createElement('div');
        el.className = 'toast';
        el.setAttribute('role', 'alert');
        el.innerHTML =
            '<div class="toast-header"><strong class="me-auto">' + esc(title) + '</strong>' +
            '<button type="button" class="btn-close" data-bs-dismiss="toast"></button></div>' +
            '<div class="toast-body">' + esc(d.requestNumber) + ' — ' + esc(d.description) + '<br>' +
            '<a href="' + esc(d.detailsUrl) + '">' + esc(T.view) + '</a></div>';
        zone.appendChild(el);
        const toast = new bootstrap.Toast(el, { delay: 7000 });
        el.addEventListener('hidden.bs.toast', () => el.remove());
        toast.show();
    }

    function setBadge(el, cls, label, base, style) {
        el.className = 'badge ' + base + ' ' + cls;
        el.textContent = label;
        el.style.cssText = style || '';
    }

    function flash(el) {
        el.classList.remove('row-flash');
        void el.offsetWidth;
        el.classList.add('row-flash');
    }

    function upsertRow(d) {
        const tbody = document.getElementById('recentRows');
        tbody.querySelector('.empty-row')?.remove();
        let tr = document.getElementById('req-' + d.id);
        if (!tr) {
            if (!d.isNew) return;
            tr = document.createElement('tr');
            tr.id = 'req-' + d.id;
            tr.innerHTML =
                '<td><a href="' + esc(d.detailsUrl) + '"></a></td>' +
                '<td class="text-truncate" style="max-width:220px"></td>' +
                '<td class="c-equip"></td><td class="c-dept"></td><td class="c-tech"></td>' +
                '<td><span class="badge prio-badge"></span></td>' +
                '<td><span class="badge status-badge"></span></td>' +
                '<td class="c-date"></td>';
            tbody.prepend(tr);
            while (tbody.rows.length > RECENT_MAX) tbody.lastElementChild.remove();
        }
        tr.querySelector('td a').textContent = d.requestNumber;
        tr.cells[1].textContent = d.description;
        tr.querySelector('.c-equip').textContent = d.equipment || '-';
        tr.querySelector('.c-dept').textContent = L(d.department) || '-';
        tr.querySelector('.c-tech').textContent = d.technician || '-';
        setBadge(tr.querySelector('.prio-badge'), d.priorityBadge, L(d.priorityLabel), 'prio-badge', d.priorityStyle);
        setBadge(tr.querySelector('.status-badge'), d.statusBadge, L(d.statusLabel), 'status-badge');
        tr.querySelector('.c-date').textContent = d.createdAt;
        flash(tr);
    }

    function upsertCritical(d) {
        const card = document.getElementById('criticalCard');
        const list = document.getElementById('criticalList');
        let li = document.getElementById('crit-' + d.id);
        const active = d.priorityCritical && d.status !== 'Closed' && d.status !== 'Cancelled';
        if (!active) {
            li?.remove();
        } else {
            if (!li) {
                li = document.createElement('li');
                li.id = 'crit-' + d.id;
                li.className = 'list-group-item d-flex justify-content-between';
                li.innerHTML = '<span><a href="' + esc(d.detailsUrl) + '">' + esc(d.requestNumber) + '</a> — <span class="c-desc"></span></span>' +
                               '<span class="badge status-badge"></span>';
                list.prepend(li);
            }
            li.querySelector('.c-desc').textContent = d.description;
            setBadge(li.querySelector('.status-badge'), d.statusBadge, L(d.statusLabel), 'status-badge');
            flash(li);
        }
        card.classList.toggle('d-none', list.children.length === 0);
    }

    function setLive(on) {
        const b = document.getElementById('liveBadge');
        b.className = 'badge rounded-pill fs-6 fw-normal ' + (on ? 'bg-success' : 'bg-secondary');
        document.getElementById('liveText').textContent = on ? T.live : T.offline;
    }

    const seen = new Set();
    async function poll() {
        try {
            const res = await fetch(STATS_URL + '?since=' + encodeURIComponent(since), { cache: 'no-store', headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error(res.status);
            const s = await res.json();
            setLive(true);
            since = s.now;
            const map = { 'stat-open': s.open, 'stat-progress': s.inProgress, 'stat-parts': s.waitingParts,
                          'stat-done': s.completedThisMonth, 'stat-equip': s.totalEquipment, 'stat-down': s.downEquipment };
            for (const [id, v] of Object.entries(map)) {
                const el = document.getElementById(id);
                if (el && el.textContent != String(v)) { el.textContent = v; flash(el); }
            }
            document.querySelectorAll('[data-status-count]').forEach(el => {
                const v = String((s.byStatus || {})[el.dataset.statusCount] ?? 0);
                if (el.textContent !== v) { el.textContent = v; flash(el); }
            });
            if (window.PmsCharts) window.PmsCharts.status(s.byStatus || {});
            for (const d of s.changes) {
                if (seen.has(d.id + ':' + d.updatedAt)) continue;
                showToast(d.isNew ? T.newRequest : T.updated + ' — ' + L(d.statusLabel), d);
                upsertRow(d);
                upsertCritical(d);
            }
        } catch {
            setLive(false);
        }
    }

    function schedule(ms) {
        clearInterval(pollTimer);
        pollTimer = setInterval(() => { if (!document.hidden) poll(); }, ms);
    }

    setLive(true);
    schedule(POLL_FAST);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

    // WebSocket path: changes arrive instantly; polling stays as a slow safety net and refreshes the counters.
    if (live && live.enabled) {
        let refresh = null;
        live.on(CHANNEL, 'request.changed', d => {
            seen.add(d.id + ':' + d.updatedAt);
            showToast(d.isNew ? T.newRequest : T.updated + ' — ' + L(d.statusLabel), d);
            upsertRow(d);
            upsertCritical(d);
            clearTimeout(refresh);
            refresh = setTimeout(poll, 400);
        });
        live.onState(on => { setLive(true); schedule(on ? POLL_SLOW : POLL_FAST); });
    }
})();
</script>
</x-slot:scripts>
</x-layouts.app>
