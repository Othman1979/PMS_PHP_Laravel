// DevExpress-style report grid built on Tabulator: sorting, per-column filters,
// grouping with per-group totals, paging, column chooser, quick search and print.
(function () {
    var el = document.getElementById('reportGrid');
    var dataEl = document.getElementById('reportData');
    if (!el || !dataEl || typeof Tabulator === 'undefined') return;

    var cfg = JSON.parse(dataEl.textContent);
    var L = cfg.lang;
    var BADGE = {
        'bg-success': '#107c10', 'bg-danger': '#c42b1c', 'bg-warning text-dark': '#fcd116', 'bg-primary': '#005fb8',
        'bg-secondary': '#616161', 'bg-dark': '#1a1a1a', 'bg-info text-dark': '#5cc3dc', 'bg-orange': '#ca5010',
        'bg-light text-dark border': '#f3f3f3', 'bg-primary-subtle text-primary-emphasis border border-primary': '#e5f0fb'
    };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function num(v, d) {
        if (v === null || v === undefined || v === '') return '';
        return Number(v).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
    }
    function textColor(hex) {
        var h = hex.replace('#', '');
        if (h.length !== 6) return '#fff';
        var r = parseInt(h.substr(0, 2), 16), g = parseInt(h.substr(2, 2), 16), b = parseInt(h.substr(4, 2), 16);
        return (r * 299 + g * 587 + b * 114) / 1000 > 150 ? '#1a1a1a' : '#fff';
    }
    function badge(value, color) {
        if (value === null || value === undefined || value === '') return '';
        var hex = color && color.charAt(0) === '#' ? color : (BADGE[color] || '#616161');
        return '<span class="badge" style="background:' + esc(hex) + ';color:' + textColor(hex) + '">' + esc(value) + '</span>';
    }

    function numeric(def, decimals, suffix, total) {
        def.sorter = 'number';
        def.hozAlign = 'right';
        def.headerHozAlign = 'right';
        def.cssClass = 'rg-num';
        def.formatter = function (cell) { var v = cell.getValue(); return v === null || v === undefined || v === '' ? '' : num(v, decimals) + (suffix || ''); };
        if (total) {
            def.bottomCalc = 'sum';
            def.bottomCalcFormatter = function (cell) { return num(cell.getValue(), decimals) + (suffix || ''); };
        }
    }

    function column(c) {
        var def = { title: c.label, field: c.key, visible: c.visible !== false, headerFilter: 'input', headerFilterPlaceholder: L.filter, minWidth: 80, headerTooltip: true };
        if (c.width) def.width = c.width;
        switch (c.type) {
            case 'int': numeric(def, 0, '', c.total); break;
            case 'money': numeric(def, 2, '', c.total); break;
            case 'percent': numeric(def, 1, '%', false); break;
            case 'hours': numeric(def, 1, '', false); break;
            case 'date': def.sorter = 'string'; def.hozAlign = 'center'; def.cssClass = 'rg-num'; break;
            case 'link':
                def.formatter = function (cell) {
                    var v = cell.getValue(), url = cell.getRow().getData()[c.urlKey];
                    if (v === null || v === undefined || v === '') return '-';
                    return url ? '<a href="' + esc(url) + '">' + esc(v) + '</a>' : esc(v);
                };
                def.cssClass = 'rg-num';
                break;
            case 'badge':
                def.hozAlign = 'center';
                def.formatter = function (cell) { return badge(cell.getValue(), cell.getRow().getData()[c.colorKey]); };
                break;
            default:
                def.tooltip = true;
        }
        return def;
    }

    var langs = {};
    langs[cfg.locale] = {
        groups: { item: L.item, items: L.items },
        data: { loading: L.loading, error: 'Error' },
        pagination: {
            page_size: L.pageSize, page_title: L.pageSize, first: L.first, first_title: L.first, last: L.last, last_title: L.last,
            prev: L.prev, prev_title: L.prev, next: L.next, next_title: L.next, all: L.all,
            counter: { showing: L.showing, of: L.of, rows: L.rows, pages: L.pages }
        }
    };

    var filtersHtml = Object.keys(cfg.filters).map(function (k) { return '<span>' + esc(k) + ': ' + esc(cfg.filters[k]) + '</span>'; }).join(' · ');

    var table = new Tabulator(el, {
        data: cfg.rows,
        columns: cfg.columns.map(column).map(function (def, i) {
            if (i === 0 && !def.bottomCalc) { def.bottomCalc = function () { return L.total; }; def.bottomCalcFormatter = 'plaintext'; }
            return def;
        }),
        layout: 'fitDataStretch',
        textDirection: cfg.rtl ? 'rtl' : 'ltr',
        locale: cfg.locale,
        langs: langs,
        pagination: true,
        paginationSize: 25,
        paginationSizeSelector: [10, 25, 50, 100, true],
        paginationCounter: 'rows',
        movableColumns: true,
        columnCalcs: 'both',
        groupBy: cfg.groupBy || false,
        groupStartOpen: true,
        groupToggleElement: 'header',
        groupHeader: function (value, count) {
            return esc(value === null || value === '' ? '-' : value) + ' <span class="rg-count">' + count + ' ' + esc(L.rows) + '</span>';
        },
        placeholder: L.noData,
        printAsHtml: true,
        printRowRange: 'all',
        printStyled: true,
        printHeader: '<div class="rg-print-head"><h2>' + esc(cfg.title) + '</h2><div class="rg-print-filters">' + filtersHtml + '</div></div>',
        printFooter: '<div class="rg-print-foot">' + esc(L.generatedAt) + ': ' + new Date().toLocaleString(cfg.locale === 'ar' ? 'ar-EG-u-nu-latn' : 'en-GB') + '</div>'
    });

    var groupSelect = document.getElementById('reportGroup');
    if (groupSelect) {
        groupSelect.addEventListener('change', function () { table.setGroupBy(this.value || false); });
    }

    var search = document.getElementById('reportSearch');
    if (search) {
        var timer;
        search.addEventListener('input', function () {
            clearTimeout(timer);
            var term = this.value.trim().toLowerCase();
            timer = setTimeout(function () {
                if (!term) { table.clearFilter(false); return; }
                table.setFilter(function (data) {
                    return Object.keys(data).some(function (k) {
                        var v = data[k];
                        return v !== null && v !== undefined && String(v).toLowerCase().indexOf(term) !== -1;
                    });
                });
            }, 150);
        });
    }

    var menu = document.getElementById('reportColumns');
    if (menu) {
        cfg.columns.forEach(function (c) {
            var id = 'col_' + c.key;
            var label = document.createElement('label');
            label.className = 'dropdown-item d-flex align-items-center gap-2';
            label.innerHTML = '<input type="checkbox" class="form-check-input m-0" id="' + id + '"' + (c.visible !== false ? ' checked' : '') + '> <span>' + esc(c.label) + '</span>';
            label.querySelector('input').addEventListener('change', function () {
                var col = table.getColumn(c.key);
                if (col) { this.checked ? col.show() : col.hide(); table.redraw(true); }
            });
            menu.appendChild(label);
        });
    }

    // Wide reports print landscape with a smaller font so the last columns (dates, duration, cost) are never cut off.
    var pageStyle = document.createElement('style');
    document.head.appendChild(pageStyle);
    function printAll() {
        var visible = table.getColumns().filter(function (c) { return c.isVisible(); }).length;
        var wide = visible > 7;
        document.body.classList.toggle('rg-print-wide', wide);
        document.body.classList.toggle('rg-print-xwide', visible > 11);
        pageStyle.textContent = '@media print { @page { size: A4 ' + (wide ? 'landscape' : 'portrait') + '; margin: 8mm; } }';
        table.print('all', true);
    }

    window.reportGrid = {
        table: table,
        print: printAll
    };
})();
